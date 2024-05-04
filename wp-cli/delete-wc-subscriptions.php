<?php
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	final class Delete_WC_Subscriptions_Command extends WP_CLI_Command {


		/**
		 * Deletes WooCommerce subscriptions of a specified status in batches and all associated data, with a confirmation and a dry-run option.
		 *
		 * ## OPTIONS
		 *
		 * [--status=<status>]
		 * : Subscription status to filter by.
		 * ---
		 * default: cancelled
		 * ---
		 *
		 * [--dry-run]
		 * : Run the command in dry-run mode to see what would be deleted without actually deleting anything.
		 *
		 * ## EXAMPLES
		 *
		 *     wp delete_wc_subscriptions --status=cancelled --dry-run
		 */
		public function __invoke( $args, $assoc_args ) {
			$status     = $assoc_args['status'];
			$dry_run    = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
			$batch_size = 1000;
			$offset     = 0;

			if ( ! $dry_run ) {
				WP_CLI::confirm( "Are you sure you want to delete subscriptions with status '{$status}'?", $assoc_args );
			}
			while ( true ) {
				$args = array(
					'subscriptions_per_page' => $batch_size,
					'subscription_status'    => $status,
					'offset'                 => $offset,
				);

				$subscriptions = wcs_get_subscriptions( $args );

				if ( empty( $subscriptions ) ) {
					break;
				}

				foreach ( $subscriptions as $subscription ) {
					if ( $dry_run ) {
						WP_CLI::log( "Would delete subscription {$subscription->get_id()} and associated user and orders." );
					} else {
						$this->delete_associated_orders( $subscription );
						$this->delete_customer_data( $subscription->get_customer_id() );
						$this->delete_subscription( $subscription );
					}
				}

				$offset += $batch_size;
				WP_CLI::log( "Processed $offset subscriptions." );
			}

			if ( $dry_run ) {
				WP_CLI::success( 'Dry run completed. No data was deleted.' );
			} else {
				WP_CLI::success( "Completed deleting subscriptions with status '$status'." );
			}
		}

		private function delete_associated_orders( $subscription ) {
			$orders = $subscription->get_related_orders();
			foreach ( $orders as $order_id ) {
				wp_delete_post( $order_id, true );
				WP_CLI::log( "Deleted order $order_id." );
			}
		}

		private function delete_customer_data( $user_id ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user_id );
			WP_CLI::log( "Deleted user and all associated data for user ID $user_id." );
		}

		private function delete_subscription( $subscription ) {
			wp_delete_post( $subscription->get_id(), true );
			WP_CLI::log( 'Deleted subscription ' . $subscription->get_id() . '.' );
		}
	}

	WP_CLI::add_command( 'delete_wc_subscriptions', 'Delete_WC_Subscriptions_Command' );
}
