<?php
/**
 * Reports view for LW ReLink.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Vs\ReLink\Admin\AdminLayout;
use Vs\ReLink\Admin\ReportsHelper;
use Vs\ReLink\Stats\StatsRepository;
use Vs\ReLink\Taxonomies\LinkGroup;
use Vs\ReLink\PostTypes\ReLink;

$days         = isset( $_GET['days'] ) ? max( 1, (int) $_GET['days'] ) : 30;
$filter_link  = ! empty( $_GET['link_id'] ) ? (int) $_GET['link_id'] : null;
$filter_group = ! empty( $_GET['group_id'] ) ? (int) $_GET['group_id'] : null;
$page         = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
$per_page     = 20;
$offset       = ( $page - 1 ) * $per_page;

$prev_days = $days * 2;

$total_clicks      = StatsRepository::get_clicks_in_period( $days, $filter_link, $filter_group );
$prev_clicks       = StatsRepository::get_clicks_in_period( $prev_days, $filter_link, $filter_group ) - $total_clicks;
$unique_visitors   = StatsRepository::get_unique_visitors( $days, $filter_link, $filter_group );
$prev_visitors     = StatsRepository::get_unique_visitors( $prev_days, $filter_link, $filter_group ) - $unique_visitors;
$active_links      = StatsRepository::get_active_links( $days, $filter_group );
$prev_active       = StatsRepository::get_active_links( $prev_days, $filter_group ) - $active_links;
$avg_per_day       = $days > 0 ? round( $total_clicks / $days, 1 ) : 0;
$prev_avg          = $days > 0 ? round( $prev_clicks / $days, 1 ) : 0;

$trends     = StatsRepository::get_click_trends( $days, $filter_link, $filter_group );
$top_links  = StatsRepository::get_top_links( 10, $days, $filter_group );
$link_stats = StatsRepository::get_paginated_link_stats( $per_page, $offset, $filter_group, $filter_link, $days );

$labels      = [];
$data_points = [];
foreach ( $trends as $trend ) {
	$labels[]      = $trend['date'];
	$data_points[] = (int) $trend['count'];
}

$all_groups = get_terms( [ 'taxonomy' => LinkGroup::TAXONOMY, 'hide_empty' => false ] );
$all_links  = get_posts( [ 'post_type' => ReLink::POST_TYPE, 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
$base_url   = admin_url( 'edit.php?post_type=lw_relink&page=' . ReportsHelper::PAGE );

/**
 * @param float $change Percentage change.
 */
$render_change = static function ( float $change ): void {
	$class  = 'flat';
	$prefix = '';
	if ( $change > 0 ) {
		$class  = 'up';
		$prefix = '+';
	} elseif ( $change < 0 ) {
		$class = 'down';
	}
	echo '<div class="lwr-change lwr-change--' . esc_attr( $class ) . '">';
	echo esc_html( $prefix . number_format_i18n( $change, 1 ) . '%' );
	echo '</div>';
};
?>
<div class="wrap lwr-admin-wrap lwr-reports-wrap">
	<?php AdminLayout::render( 'reports' ); ?>

	<div class="lwr-page-header">
		<h1><?php esc_html_e( 'Reports', 'vs-relink' ); ?></h1>
	</div>

	<?php if ( $filter_link ) : ?>
		<div class="lwr-active-filter">
			<?php
			printf(
				/* translators: %s: link title */
				esc_html__( 'Filtered by: %s', 'vs-relink' ),
				esc_html( get_the_title( $filter_link ) ?: __( '(deleted)', 'vs-relink' ) )
			);
			?>
			<a href="<?php echo esc_url( ReportsHelper::filter_url( null, $filter_group, $days ) ); ?>">× <?php esc_html_e( 'Clear', 'vs-relink' ); ?></a>
		</div>
	<?php endif; ?>

	<div class="lwr-summary-grid">
		<div class="lwr-summary-card lwr-summary-card--blue">
			<h3><?php esc_html_e( 'Total Clicks', 'vs-relink' ); ?></h3>
			<div class="lwr-value"><?php echo esc_html( number_format_i18n( $total_clicks ) ); ?></div>
			<?php $render_change( StatsRepository::percent_change( $total_clicks, max( 0, $prev_clicks ) ) ); ?>
		</div>
		<div class="lwr-summary-card lwr-summary-card--green">
			<h3><?php esc_html_e( 'Unique Visitors', 'vs-relink' ); ?></h3>
			<div class="lwr-value"><?php echo esc_html( number_format_i18n( $unique_visitors ) ); ?></div>
			<?php $render_change( StatsRepository::percent_change( $unique_visitors, max( 0, $prev_visitors ) ) ); ?>
		</div>
		<div class="lwr-summary-card lwr-summary-card--orange">
			<h3><?php esc_html_e( 'Avg. Clicks / Day', 'vs-relink' ); ?></h3>
			<div class="lwr-value"><?php echo esc_html( number_format_i18n( $avg_per_day, 1 ) ); ?></div>
			<?php $render_change( StatsRepository::percent_change( (int) round( $avg_per_day * 10 ), (int) round( $prev_avg * 10 ) ) ); ?>
		</div>
		<div class="lwr-summary-card lwr-summary-card--purple">
			<h3><?php esc_html_e( 'Active Links', 'vs-relink' ); ?></h3>
			<div class="lwr-value"><?php echo esc_html( number_format_i18n( $active_links ) ); ?></div>
			<?php $render_change( StatsRepository::percent_change( $active_links, max( 0, $prev_active ) ) ); ?>
		</div>
	</div>

	<div class="lwr-panel">
		<div class="lwr-panel-header">
			<h2>
				<?php
				if ( $filter_link ) {
					printf(
						/* translators: %s: link title */
						esc_html__( 'Clicks Over Time — %s', 'vs-relink' ),
						esc_html( get_the_title( $filter_link ) )
					);
				} else {
					esc_html_e( 'Clicks Over Time', 'vs-relink' );
				}
				?>
			</h2>
			<form method="get" class="lwr-filters">
				<input type="hidden" name="post_type" value="lw_relink" />
				<input type="hidden" name="page" value="<?php echo esc_attr( ReportsHelper::PAGE ); ?>" />
				<?php if ( $filter_link ) : ?>
					<input type="hidden" name="link_id" value="<?php echo esc_attr( (string) $filter_link ); ?>" />
				<?php endif; ?>
				<select name="group_id">
					<option value=""><?php esc_html_e( 'All Groups', 'vs-relink' ); ?></option>
					<?php foreach ( $all_groups as $group ) : ?>
						<option value="<?php echo esc_attr( (string) $group->term_id ); ?>" <?php selected( $filter_group, $group->term_id ); ?>>
							<?php echo esc_html( $group->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<select name="link_id" <?php echo $filter_link ? 'style="display:none;"' : ''; ?>>
					<option value=""><?php esc_html_e( 'All Links', 'vs-relink' ); ?></option>
					<?php foreach ( $all_links as $link ) : ?>
						<option value="<?php echo esc_attr( (string) $link->ID ); ?>" <?php selected( $filter_link, $link->ID ); ?>>
							<?php echo esc_html( $link->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<select name="days">
					<option value="7" <?php selected( $days, 7 ); ?>><?php esc_html_e( 'Last 7 days', 'vs-relink' ); ?></option>
					<option value="30" <?php selected( $days, 30 ); ?>><?php esc_html_e( 'Last 30 days', 'vs-relink' ); ?></option>
					<option value="90" <?php selected( $days, 90 ); ?>><?php esc_html_e( 'Last 90 days', 'vs-relink' ); ?></option>
					<option value="365" <?php selected( $days, 365 ); ?>><?php esc_html_e( 'Last 365 days', 'vs-relink' ); ?></option>
				</select>
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Apply', 'vs-relink' ); ?></button>
				<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Reset', 'vs-relink' ); ?></a>
			</form>
		</div>
		<div class="lwr-chart-wrap">
			<canvas id="relinkTrendsChart" height="120"></canvas>
		</div>
	</div>

	<div class="lwr-lists-grid">
		<div class="lwr-panel">
			<div class="lwr-panel-header">
				<h2><?php esc_html_e( 'Performance Details', 'vs-relink' ); ?></h2>
				<span class="lwr-badge lwr-badge--blue"><?php esc_html_e( 'This period', 'vs-relink' ); ?></span>
			</div>
			<table class="wp-list-table widefat fixed striped lwr-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Link Name', 'vs-relink' ); ?></th>
						<th><?php esc_html_e( 'Clicks', 'vs-relink' ); ?></th>
						<th><?php esc_html_e( 'Last Activity', 'vs-relink' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $link_stats ) ) : ?>
						<?php foreach ( $link_stats as $link ) : ?>
							<?php
							$link_id    = (int) $link['ID'];
							$click_cnt  = (int) $link['click_count'];
							$is_active  = $filter_link === $link_id;
							?>
							<tr>
								<td>
									<strong>
										<a href="<?php echo esc_url( get_edit_post_link( $link_id ) ); ?>">
											<?php echo esc_html( $link['post_title'] ); ?>
										</a>
									</strong>
								</td>
								<td>
									<?php
									if ( $click_cnt > 0 ) {
										$class = 'lwr-click-count' . ( $is_active ? ' lwr-click-count--active' : '' );
										echo '<a href="' . esc_url( ReportsHelper::filter_url( $link_id, $filter_group, $days ) ) . '" class="' . esc_attr( $class ) . '">';
										echo esc_html( number_format_i18n( $click_cnt ) );
										echo '</a>';
									} else {
										echo '<span class="lwr-click-count lwr-click-count--zero">0</span>';
									}
									?>
								</td>
								<td>
									<?php
									if ( ! empty( $link['last_click'] ) ) {
										printf(
											/* translators: %s: human-readable time difference */
											esc_html__( '%s ago', 'vs-relink' ),
											esc_html( human_time_diff( strtotime( (string) $link['last_click'] ) ) )
										);
									} else {
										esc_html_e( 'Never', 'vs-relink' );
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="3"><?php esc_html_e( 'No links found for the selected criteria.', 'vs-relink' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php if ( $page > 1 ) : ?>
						<a class="prev-page button" href="<?php echo esc_url( add_query_arg( 'paged', $page - 1 ) ); ?>">‹</a>
					<?php endif; ?>
					<span class="paging-input"><?php echo esc_html( (string) $page ); ?></span>
					<?php if ( count( $link_stats ) >= $per_page ) : ?>
						<a class="next-page button" href="<?php echo esc_url( add_query_arg( 'paged', $page + 1 ) ); ?>">›</a>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="lwr-panel">
			<div class="lwr-panel-header">
				<h2><?php esc_html_e( 'Top Links', 'vs-relink' ); ?></h2>
				<span class="lwr-badge lwr-badge--green"><?php esc_html_e( 'Top 10', 'vs-relink' ); ?></span>
			</div>
			<ol class="lwr-ranked-list">
				<?php if ( empty( $top_links ) ) : ?>
					<li><?php esc_html_e( 'No data found.', 'vs-relink' ); ?></li>
				<?php else : ?>
					<?php foreach ( $top_links as $index => $top ) : ?>
						<?php
						$top_id   = (int) $top['ID'];
						$top_cnt  = (int) $top['click_count'];
						$is_active = $filter_link === $top_id;
						?>
						<li>
							<span class="lwr-rank"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span>
							<div class="lwr-ranked-main">
								<strong><?php echo esc_html( $top['post_title'] ); ?></strong>
							</div>
							<?php if ( $top_cnt > 0 ) : ?>
								<a href="<?php echo esc_url( ReportsHelper::filter_url( $top_id, $filter_group, $days ) ); ?>" class="lwr-click-count<?php echo $is_active ? ' lwr-click-count--active' : ''; ?>">
									<?php echo esc_html( number_format_i18n( $top_cnt ) ); ?>
								</a>
							<?php else : ?>
								<span class="lwr-click-count lwr-click-count--zero">0</span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				<?php endif; ?>
			</ol>
		</div>
	</div>

	<?php AdminLayout::render_end(); ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
jQuery(function($) {
	var ctx = document.getElementById('relinkTrendsChart');
	if (!ctx) return;

	new Chart(ctx.getContext('2d'), {
		type: 'bar',
		data: {
			labels: <?php echo wp_json_encode( $labels ); ?>,
			datasets: [{
				label: <?php echo wp_json_encode( __( 'Clicks', 'vs-relink' ) ); ?>,
				data: <?php echo wp_json_encode( $data_points ); ?>,
				backgroundColor: 'rgba(79, 140, 255, 0.85)',
				borderRadius: 6,
				borderSkipped: false
			}]
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			scales: {
				y: { beginAtZero: true, grid: { color: '#f0f0f1' } },
				x: { grid: { display: false } }
			},
			plugins: { legend: { display: false } }
		}
	});
});
</script>
