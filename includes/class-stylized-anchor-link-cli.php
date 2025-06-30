<?php
/**
 * WP-CLI Command for Stylized Anchor Link
 *
 * @package StylizedAnchorLink
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CLI commands for Stylized Anchor Link plugin.
 */
class Stylized_Anchor_Link_CLI extends WP_CLI_Command {

    /**
     * Search for posts containing the Stylized Anchor Link block within a date range.
     *
     * ## OPTIONS
     *
     * [--date-after=<date>]
     * : Find posts published after this date (format: YYYY-MM-DD). Default is 30 days ago.
     *
     * [--date-before=<date>]
     * : Find posts published before this date (format: YYYY-MM-DD). Default is today.
     *
     * [--batch-size=<number>]
     * : Number of posts to process in each batch. Default is 5000.
     *
     * [--limit=<number>]
     * : Limit the total number of posts to process. Default is no limit.
     *
     * ## EXAMPLES
     *
     *     # Search for posts with Stylized Anchor Link blocks in the last 30 days
     *     $ wp dmg-read-more search
     *
     *     # Search for posts with Stylized Anchor Link blocks in a specific date range
     *     $ wp dmg-read-more search --date-after=2023-01-01 --date-before=2023-12-31
     *
     *     # Search with a custom batch size for performance tuning
     *     $ wp dmg-read-more search --batch-size=10000
     *
     * @param array $args       Command arguments.
     * @param array $assoc_args Command options.
     */
    public function search( $args, $assoc_args ) {
        global $wpdb;
        
        // Set default date range (last 30 days)
        $date_before = isset( $assoc_args['date-before'] ) ? $assoc_args['date-before'] : date( 'Y-m-d' );
        $date_after = isset( $assoc_args['date-after'] ) ? $assoc_args['date-after'] : date( 'Y-m-d', strtotime( '-30 days' ) );
        
        // Get batch size from arguments or use default
        $batch_size = isset( $assoc_args['batch-size'] ) ? (int) $assoc_args['batch-size'] : 5000;
        
        // Get limit from arguments
        $limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 0;

        // Validate dates
        if ( ! $this->validate_date( $date_before ) || ! $this->validate_date( $date_after ) ) {
            WP_CLI::error( 'Invalid date format. Please use YYYY-MM-DD.' );
            return;
        }

        WP_CLI::log( sprintf( 'Searching for posts with Stylized Anchor Link blocks between %s and %s...', $date_after, $date_before ) );

        // Start timing the operation
        $start_time = microtime( true );
        
        // OPTIMIZATION: Combined query to find posts with both date range and block content
        // This avoids loading all post IDs in the date range into memory
        $matching_posts = [];
        $offset = 0;
        $total_found = 0;
        
        // Create a temporary table for better performance with large datasets
        $temp_table_name = $wpdb->prefix . 'tmp_stylized_anchor_search_' . wp_rand();
        
        // Check if we can create temporary tables
        $can_use_temp_table = false;
        $temp_table_check = $wpdb->query( "CREATE TEMPORARY TABLE IF NOT EXISTS `{$temp_table_name}_test` (id INT)" );
        if ( $temp_table_check !== false ) {
            $wpdb->query( "DROP TEMPORARY TABLE IF EXISTS `{$temp_table_name}_test`" );
            $can_use_temp_table = true;
        }
        
        if ( $can_use_temp_table ) {
            // Create temporary table for results
            $wpdb->query( "CREATE TEMPORARY TABLE `{$temp_table_name}` (post_id BIGINT UNSIGNED NOT NULL PRIMARY KEY)" );
            
            // Use a single query to find matching posts and insert into temp table
            $wpdb->query( $wpdb->prepare( 
                "INSERT IGNORE INTO `{$temp_table_name}` (post_id)
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'post' 
                AND post_status = 'publish' 
                AND post_date >= %s 
                AND post_date <= %s
                AND (
                    post_content LIKE %s 
                    OR post_content LIKE %s
                )",
                $date_after . ' 00:00:00',
                $date_before . ' 23:59:59',
                '%<!-- wp:stylized-anchor-link%',
                '%\"blockName\":\"stylized-anchor-link%'
            ) );
            
            // Count total results
            $total_found = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$temp_table_name}`" );
            
            // Process in batches
            while ( true ) {
                $batch_results = $wpdb->get_col( $wpdb->prepare( 
                    "SELECT post_id FROM `{$temp_table_name}` LIMIT %d, %d", 
                    $offset, 
                    $batch_size 
                ) );
                
                if ( empty( $batch_results ) ) {
                    break;
                }
                
                $matching_posts = array_merge( $matching_posts, $batch_results );
                $offset += count( $batch_results );
                
                // Show progress
                WP_CLI::log( sprintf( 'Processed %d of %d matching posts (%.1f%%)', 
                    count( $matching_posts ), 
                    $total_found, 
                    ( count( $matching_posts ) / $total_found * 100 ) 
                ) );
                
                // Check if we've reached the limit
                if ( $limit > 0 && count( $matching_posts ) >= $limit ) {
                    $matching_posts = array_slice( $matching_posts, 0, $limit );
                    break;
                }
            }
            
            // Clean up temporary table
            $wpdb->query( "DROP TEMPORARY TABLE IF EXISTS `{$temp_table_name}`" );
        } else {
            // Fallback method if temporary tables aren't available
            // Process in batches using LIMIT and OFFSET for pagination
            while ( true ) {
                $limit_clause = $limit > 0 ? " LIMIT " . ($limit - count($matching_posts)) : "";
                
                $query = $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} 
                    WHERE post_type = 'post' 
                    AND post_status = 'publish' 
                    AND post_date >= %s 
                    AND post_date <= %s
                    AND (
                        post_content LIKE %s 
                        OR post_content LIKE %s
                    )
                    LIMIT %d OFFSET %d",
                    $date_after . ' 00:00:00',
                    $date_before . ' 23:59:59',
                    '%<!-- wp:stylized-anchor-link%',
                    '%\"blockName\":\"stylized-anchor-link%',
                    $batch_size,
                    $offset
                );
                
                $batch_results = $wpdb->get_col( $query );
                
                if ( empty( $batch_results ) ) {
                    break;
                }
                
                $matching_posts = array_merge( $matching_posts, $batch_results );
                $offset += count( $batch_results );
                
                // Show progress
                WP_CLI::log( sprintf( 'Found %d posts with Stylized Anchor Link blocks so far...', count( $matching_posts ) ) );
                
                // Check if we've reached the limit
                if ( $limit > 0 && count( $matching_posts ) >= $limit ) {
                    $matching_posts = array_slice( $matching_posts, 0, $limit );
                    break;
                }
            }
            
            $total_found = count( $matching_posts );
        }
        
        // Calculate execution time
        $execution_time = microtime( true ) - $start_time;

        // Output results
        if ( empty( $matching_posts ) ) {
            WP_CLI::log( 'No posts containing Stylized Anchor Link blocks were found in the specified date range.' );
        } else {
            WP_CLI::log( sprintf( 'Found %d posts containing Stylized Anchor Link blocks:', count( $matching_posts ) ) );
            
            foreach ( $matching_posts as $post_id ) {
                // Output just the post ID as required
                WP_CLI::log( $post_id );
            }
            
            WP_CLI::success( sprintf( 
                'Successfully found %d posts with Stylized Anchor Link blocks in %.2f seconds.', 
                count( $matching_posts ),
                $execution_time
            ) );
        }
    }

    /**
     * Validate date format
     *
     * @param string $date Date string to validate.
     * @return bool Whether the date is valid.
     */
    private function validate_date( $date ) {
        $d = DateTime::createFromFormat( 'Y-m-d', $date );
        return $d && $d->format( 'Y-m-d' ) === $date;
    }
}