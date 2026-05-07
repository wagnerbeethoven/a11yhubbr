<?php
/*
Template Name: Rede
*/
if (!defined('ABSPATH')) {
  exit;
}

$community_types = array(
  'comunidades' => array(
    'label' => 'Comunidades',
    'icon' => 'fa-solid fa-users',
    'aliases' => array('Comunidade', 'Comunidades', 'Rede', 'Redes'),
  ),
  'profissionais-tecnologia' => array(
    'label' => 'Profissionais de IT',
    'icon' => 'fa-solid fa-briefcase',
    'aliases' => array('Profissional de tecnologia', 'Profissionais de tecnologia', 'Profissionais de IT'),
  ),
  'empresas-ongs' => array(
    'label' => 'Empresas e ONGs',
    'icon' => 'fa-regular fa-building',
    'aliases' => array('Empresa ou ONG', 'Empresas e ONGs'),
  ),
  'interpretes-libras' => array(
    'label' => 'Intérpretes de Libras',
    'icon' => 'fa-regular fa-hand',
    'aliases' => array('Intérprete de Libras', 'Interprete de Libras', 'Intérpretes de Libras'),
  ),
  'audiodescritores' => array(
    'label' => 'Audiodescritores',
    'icon' => 'fa-regular fa-eye',
    'aliases' => array('Audiodescritor', 'Audiodescritores'),
  ),
  'tradutores-braille' => array(
    'label' => 'Transcritores de Braille',
    'icon' => 'fa-solid fa-braille',
    'aliases' => array('Tradutor de Braille', 'Transcritores de Braille'),
  ),
);

$slug_from_value = static function ($value) use ($community_types) {
  $normalized = sanitize_title((string) $value);

  foreach ($community_types as $slug => $type) {
    if ($normalized === sanitize_title($slug) || $normalized === sanitize_title($type['label'])) {
      return $slug;
    }

    foreach ($type['aliases'] as $alias) {
      if ($normalized === sanitize_title($alias)) {
        return $slug;
      }
    }
  }

  return '';
};

$meta_values_by_slug = array();
foreach ($community_types as $slug => $type) {
  $values = array($type['label']);
  foreach ($type['aliases'] as $alias) {
    $values[] = $alias;
  }
  $meta_values_by_slug[$slug] = array_values(array_unique($values));
}

$raw_type = isset($_GET['tipo']) ? sanitize_text_field(wp_unslash($_GET['tipo'])) : '';
$selected_type = $raw_type !== '' ? $slug_from_value($raw_type) : '';

$allowed_sort = array('nome_az', 'nome_za', 'recentes');
$sort = isset($_GET['ordem']) ? sanitize_key(wp_unslash($_GET['ordem'])) : 'nome_az';
if (!in_array($sort, $allowed_sort, true)) {
  $sort = 'nome_az';
}

$allowed_per_page = array(8, 12, 24);
$per_page = isset($_GET['itens']) ? absint($_GET['itens']) : 8;
if (!in_array($per_page, $allowed_per_page, true)) {
  $per_page = 8;
}

$search_term = isset($_GET['busca']) ? sanitize_text_field(wp_unslash($_GET['busca'])) : '';

$paged = isset($_GET['pg']) ? absint($_GET['pg']) : 1;
if ($paged < 1) {
  $paged = 1;
}

$order = 'ASC';
$orderby = 'title';
if ($sort === 'nome_za') {
  $order = 'DESC';
} elseif ($sort === 'recentes') {
  $order = 'DESC';
  $orderby = 'date';
}

$query_args = array(
  'post_type' => 'a11y_perfil',
  'post_status' => 'publish',
  'paged' => $paged,
  'posts_per_page' => $per_page,
  'orderby' => $orderby,
  'order' => $order,
);

if ($selected_type !== '' && isset($meta_values_by_slug[$selected_type])) {
  $query_args['meta_query'] = array(
    array(
      'key' => '_a11yhubbr_profile_type',
      'value' => $meta_values_by_slug[$selected_type],
      'compare' => 'IN',
    ),
  );
}

if ($search_term !== '') {
  $search_ids = function_exists('a11yhubbr_find_posts_by_term')
    ? a11yhubbr_find_posts_by_term(
      'a11y_perfil',
      $search_term,
      array(
        '_a11yhubbr_profile_type',
        '_a11yhubbr_profile_role',
        '_a11yhubbr_profile_location',
        '_a11yhubbr_profile_website',
        '_a11yhubbr_profile_social_links',
      )
    )
    : array();

  $query_args['post__in'] = !empty($search_ids) ? $search_ids : array(0);
}

$profiles_query = new WP_Query($query_args);

$type_counts = array();
foreach ($community_types as $slug => $type) {
  $count_query = new WP_Query(array(
    'post_type' => 'a11y_perfil',
    'post_status' => 'publish',
    'posts_per_page' => 1,
    'fields' => 'ids',
    'meta_query' => array(
      array(
        'key' => '_a11yhubbr_profile_type',
        'value' => $meta_values_by_slug[$slug],
        'compare' => 'IN',
      ),
    ),
  ));
  $type_counts[$slug] = (int) $count_query->found_posts;
}

$base_url = get_permalink();
$current_args = array(
  'tipo' => $selected_type,
  'ordem' => $sort,
  'itens' => $per_page,
  'busca' => $search_term,
);

$build_url = static function ($overrides = array ()) use ($base_url, $current_args) {
  $args = array_merge($current_args, $overrides);

  if (isset($args['tipo']) && $args['tipo'] === '') {
    unset($args['tipo']);
  }
  if (isset($args['busca']) && $args['busca'] === '') {
    unset($args['busca']);
  }
  if (isset($args['ordem']) && $args['ordem'] === 'nome_az') {
    unset($args['ordem']);
  }
  if (isset($args['itens']) && (int) $args['itens'] === 8) {
    unset($args['itens']);
  }
  if (isset($args['pg']) && is_numeric($args['pg']) && (int) $args['pg'] <= 1) {
    unset($args['pg']);
  }

  return add_query_arg($args, $base_url);
};

$title_suffix = ($selected_type !== '' && isset($community_types[$selected_type])) ? ': ' . $community_types[$selected_type]['label'] : '';
$has_active_filters = ($selected_type !== '' || $search_term !== '' || $sort !== 'nome_az' || $per_page !== 8);

get_header();
?>
<main id="conteudo-principal" tabindex="-1" class="a11yhubbr-site-main a11yhubbr-community-page">
  <?php
  a11yhubbr_render_page_header(array(
    'breadcrumbs' => array(
      array('label' => 'Página inicial', 'url' => home_url('/')),
      array('label' => 'Rede'),
    ),
    'icon' => 'fa-solid fa-circle-nodes',
  ));
  ?>

  <section class="a11yhubbr-section">
    <div class="a11yhubbr-container">
      <h2 class="a11yhubbr-content-heading">Navegue por categoria</h2>
      <div class="a11yhubbr-content-types-grid a11yhubbr-community-types-grid">
        <?php foreach ($community_types as $slug => $type): ?>
          <?php
          $is_active = $selected_type === $slug;
          $item_count = isset($type_counts[$slug]) ? (int) $type_counts[$slug] : 0;
          $count_label = $item_count === 1 ? '1 perfil' : $item_count . ' perfis';
          $type_url = $is_active
            ? $build_url(array('tipo' => '', 'pg' => 1))
            : $build_url(array('tipo' => $slug, 'pg' => 1));
          ?>
          <a class="a11yhubbr-content-type-card<?php echo $is_active ? ' is-active' : ''; ?>"
            href="<?php echo esc_url($type_url); ?>" aria-current="<?php echo $is_active ? 'true' : 'false'; ?>">
            <span class="a11yhubbr-content-type-icon" aria-hidden="true"><i
                class="<?php echo esc_attr($type['icon']); ?>"></i></span>
            <strong><?php echo esc_html($type['label']); ?></strong>
            <span><?php echo esc_html($count_label); ?></span>
          </a>
        <?php endforeach; ?>
      </div>

      <?php get_template_part('inc/components/archive-toolbar', null, array(
        'heading' => 'Perfis' . $title_suffix,
        'base_url' => $base_url,
        'selected_type' => $selected_type,
        'show_type_input' => ($selected_type !== '' && isset($community_types[$selected_type])),
        'search_term' => $search_term,
        'clear_search_url' => $build_url(array('busca' => '', 'pg' => 1)),
        'sort_name' => 'ordem',
        'sort_options' => array(
          'nome_az' => 'Nome A-Z',
          'nome_za' => 'Nome Z-A',
          'recentes' => 'Mais recentes',
        ),
        'current_sort' => $sort,
        'per_page_name' => 'itens',
        'per_page_options' => $allowed_per_page,
        'current_per_page' => $per_page,
        'per_page_label_suffix' => 'perfis',
        'show_reset' => $has_active_filters,
        'reset_url' => $build_url(array('tipo' => '', 'busca' => '', 'ordem' => 'nome_az', 'itens' => 8, 'pg' => 1)),
        'reset_label' => 'Limpar filtros',
      )); ?>

      <?php if ($profiles_query->have_posts()): ?>
        <div class="a11yhubbr-community-profiles-grid">
          <?php while ($profiles_query->have_posts()):
            $profiles_query->the_post(); ?>
            <?php
            $profile_type_raw = (string) get_post_meta(get_the_ID(), '_a11yhubbr_profile_type', true);
            $profile_type_slug = $slug_from_value($profile_type_raw);
            $profile_type_label = ($profile_type_slug !== '' && isset($community_types[$profile_type_slug]))
              ? $community_types[$profile_type_slug]['label']
              : ($profile_type_raw !== '' ? $profile_type_raw : 'Perfil');
            ?>
            <?php get_template_part('inc/components/profile-card', null, array(
              'post_id' => get_the_ID(),
              'badge_label' => $profile_type_label,
              'details_url' => get_permalink(),
              'show_details_link' => true,
              'details_label' => 'Ver detalhes',
              'show_external_link' => true,
              'external_label' => 'Site',
              'show_social' => true,
            )); ?>
          <?php endwhile; ?>
        </div>

        <?php
        $pagination_base = $build_url(array('pg' => '%#%'));
        $pagination_base = str_replace(array('%2523', '%23'), '%#%', $pagination_base);

        $pagination = paginate_links(array(
          'base' => $pagination_base,
          'format' => '',
          'current' => $paged,
          'total' => max(1, (int) $profiles_query->max_num_pages),
          'type' => 'array',
          'prev_text' => '&lsaquo; Anterior',
          'next_text' => 'Próxima &rsaquo;',
        ));
        ?>

        <?php if (!empty($pagination)): ?>
          <nav class="a11yhubbr-content-pagination" aria-label="Paginação de perfis">
            <?php foreach ($pagination as $page_link): ?>
              <?php echo wp_kses_post($page_link); ?>
            <?php endforeach; ?>
          </nav>
        <?php endif; ?>
      <?php else: ?>
        <?php get_template_part('inc/components/empty-state', null, array(
          'title' => 'Nenhum perfil encontrado',
          'message' => 'N?o encontramos perfis para os filtros selecionados.',
          'cta_label' => 'Submeter perfil',
          'cta_url' => function_exists('a11yhubbr_get_submit_profile_url') ? a11yhubbr_get_submit_profile_url() : home_url('/submeter/submeter-perfil'),
          'cta_class' => 'a11yhubbr-btn-context',
          'icon' => 'fa-regular fa-id-card',
        )); ?>
      <?php endif; ?>


      <?php wp_reset_postdata(); ?>
    </div>
  </section>


</main>
<?php get_footer(); ?>
