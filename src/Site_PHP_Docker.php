<?php

namespace EE\Site\Type;

use function EE\Utils\mustache_render;

class Site_PHP_Docker {

	/**
	 * Generate docker-compose.yml according to requirement.
	 *
	 * @param array $filters Array of flags to determine the docker-compose.yml generation.
	 *                       Empty/Default -> Generates default WordPress docker-compose.yml
	 *                       ['le']        -> Enables letsencrypt in the generation.
	 *
	 * @return String docker-compose.yml content string.
	 */
	public function generate_docker_compose_yml( array $filters = [], $volumes ) {
		$img_versions = \EE\Utils\get_image_versions();
		$base         = [];

		$restart_default = [ 'name' => 'always' ];
		$network_default = [
			'net' => [
				[ 'name' => 'site-network' ],
			],
		];

		if ( in_array( 'db', $filters, true ) ) {
			// db configuration.
			$db['service_name'] = [ 'name' => 'db' ];
			$db['image']        = [ 'name' => 'easyengine/mariadb:' . $img_versions['easyengine/mariadb'] ];
			$db['restart']      = $restart_default;
			$db['labels']       = [
				'label' => [
					'name' => 'io.easyengine.site=${VIRTUAL_HOST}',
				],
			];
			$db['volumes']      = [
				[
					'vol' => \EE_DOCKER::get_mounting_volume_array( $volumes['db'] ),
				],
			];
			$db['environment']  = [
				'env' => [
					[ 'name' => 'MYSQL_ROOT_PASSWORD' ],
					[ 'name' => 'MYSQL_DATABASE' ],
					[ 'name' => 'MYSQL_USER' ],
					[ 'name' => 'MYSQL_PASSWORD' ],
				],
			];
			$db['networks']     = $network_default;
		}
		// PHP configuration.
		$php_image_key = ( 5.6 === $filters['php_version'] ? 'easyengine/php5.6' : 'easyengine/php' );

		$php['service_name'] = [ 'name' => 'php' ];
		$php['image']        = [ 'name' => $php_image_key . ':' . $img_versions[ $php_image_key ] ];

		if ( in_array( 'db', $filters, true ) ) {
			$php['depends_on']['dependency'][] = [ 'name' => 'db' ];
		}

		if ( in_array( 'redis', $filters, true ) ) {
			$php['depends_on']['dependency'][] = [ 'name' => 'redis' ];
		}

		$php['restart']     = $restart_default;
		$php['labels']      = [
			'label' => [
				'name' => 'io.easyengine.site=${VIRTUAL_HOST}',
			],
		];
		$php['volumes']     = [
			[
				'vol' => \EE_DOCKER::get_mounting_volume_array( $volumes['php'] ),
			],
		];
		$php['environment'] = [
			'env' => [
				[ 'name' => 'USER_ID' ],
				[ 'name' => 'GROUP_ID' ],
				[ 'name' => 'VIRTUAL_HOST' ],
			],
		];
		$php['networks']    = [
			'net' => [
				[
					'name'    => 'site-network',
					'aliases' => [
						'alias' => [
							'name' => '${VIRTUAL_HOST}_php',
						],
					],
				],
			],
		];

		if ( in_array( GLOBAL_DB, $filters, true ) ) {
			$php['networks']['net'][] = [ 'name' => 'global-backend-network' ];
		}

		// nginx configuration.
		$nginx['service_name']               = [ 'name' => 'nginx' ];
		$nginx['image']                      = [ 'name' => 'easyengine/nginx:' . $img_versions['easyengine/nginx'] ];
		$nginx['depends_on']['dependency'][] = [ 'name' => 'php' ];
		$nginx['restart']                    = $restart_default;

		$v_host = 'VIRTUAL_HOST';

		$nginx['environment'] = [
			'env' => [
				[ 'name' => $v_host ],
				[ 'name' => 'VIRTUAL_PATH=/' ],
				[ 'name' => 'HSTS=off' ],
			],
		];
		if ( ! empty( $filters['nohttps'] ) && $filters['nohttps'] ) {
			$nginx['environment']['env'][] = [ 'name' => 'HTTPS_METHOD=nohttps' ];
		}
		$nginx['volumes']  = [
			'vol' => \EE_DOCKER::get_mounting_volume_array( $volumes['nginx'] ),
		];
		$nginx['labels']   = [
			'label' => [
				'name' => 'io.easyengine.site=${VIRTUAL_HOST}',
			],
		];
		$nginx['networks'] = [
			'net' => [
				[ 'name' => 'global-frontend-network' ],
				[ 'name' => 'site-network' ],
			],
		];
		if ( in_array( GLOBAL_REDIS, $filters, true ) ) {
			$nginx['networks']['net'][] = [ 'name' => 'global-backend-network' ];
		}
		// mailhog configuration.
		$mailhog['service_name'] = [ 'name' => 'mailhog' ];
		$mailhog['image']        = [ 'name' => 'easyengine/mailhog:' . $img_versions['easyengine/mailhog'] ];
		$mailhog['restart']      = $restart_default;
		$mailhog['command']      = [ 'name' => '["-invite-jim=false"]' ];
		$mailhog['environment']  = [
			'env' => [
				[ 'name' => $v_host ],
				[ 'name' => 'VIRTUAL_PATH=/ee-admin/mailhog/' ],
				[ 'name' => 'VIRTUAL_PORT=8025' ],
			],
		];
		$mailhog['labels']       = [
			'label' => [
				'name' => 'io.easyengine.site=${VIRTUAL_HOST}',
			],
		];
		$mailhog['networks']     = [
			'net' => [
				[ 'name' => 'site-network' ],
				[ 'name' => 'global-frontend-network' ],
			],
		];

		// postfix configuration.
		$postfix['service_name'] = [ 'name' => 'postfix' ];
		$postfix['image']        = [ 'name' => 'easyengine/postfix:' . $img_versions['easyengine/postfix'] ];
		$postfix['hostname']     = [ 'name' => '${VIRTUAL_HOST}' ];
		$postfix['restart']      = $restart_default;
		$postfix['labels']       = [
			'label' => [
				'name' => 'io.easyengine.site=${VIRTUAL_HOST}',
			],
		];
		$postfix['volumes']      = [
			'vol' => \EE_DOCKER::get_mounting_volume_array( $volumes['postfix'] ),
		];
		$postfix['networks']     = $network_default;

		// redis configuration.
		$redis['service_name'] = [ 'name' => 'redis' ];
		$redis['image']        = [ 'name' => 'easyengine/redis:' . $img_versions['easyengine/redis'] ];
		$redis['labels']       = [
			'label' => [
				'name' => 'io.easyengine.site=${VIRTUAL_HOST}',
			],
		];
		$redis['networks']     = $network_default;

		$base[] = $php;
		$base[] = $nginx;
		$base[] = $mailhog;
		$base[] = $postfix;

		if ( in_array( 'redis', $filters, true ) ) {
			$base[] = $redis;
		}

		$external_volumes = [
			'external_vols' => [
				[ 'prefix' => $filters['site_prefix'], 'ext_vol_name' => 'htdocs' ],
				[ 'prefix' => $filters['site_prefix'], 'ext_vol_name' => 'config_nginx' ],
				[ 'prefix' => $filters['site_prefix'], 'ext_vol_name' => 'config_php' ],
				[ 'prefix' => $filters['site_prefix'], 'ext_vol_name' => 'log_php' ],
				[ 'prefix' => $filters['site_prefix'], 'ext_vol_name' => 'log_nginx' ],
				[ 'prefix' => $filters['site_prefix'], 'ext_vol_name' => 'data_postfix' ],
				[ 'prefix' => $filters['site_prefix'], 'ext_vol_name' => 'ssl_postfix' ],
				[ 'prefix' => $filters['site_prefix'], 'ext_vol_name' => 'config_postfix' ],
			],
		];

		$network = [
			'networks_labels' => [
				'label' => [
					[ 'name' => 'org.label-schema.vendor=EasyEngine' ],
					[ 'name' => 'io.easyengine.site=${VIRTUAL_HOST}' ],
				],
			],
		];

		if ( in_array( 'db', $filters, true ) ) {
			$base[]                            = $db;
			$external_volumes['external_vols'] = array_merge( $external_volumes['external_vols'], [
				[ 'prefix' => $filters['site_prefix'], 'ext_vol_name' => 'db_data' ],
				[ 'prefix' => $filters['site_prefix'], 'ext_vol_name' => 'db_conf' ],
				[ 'prefix' => $filters['site_prefix'], 'ext_vol_name' => 'db_logs' ],
			] );
		} else {
			$network['enable_backend_network'] = true;
		}

		$binding = [
			'services'        => $base,
			'network'         => $network,
		];

		if ( ! IS_DARWIN ) {
			$binding['created_volumes'] = $external_volumes;
		}

		$docker_compose_yml = mustache_render( SITE_PHP_TEMPLATE_ROOT . '/docker-compose.mustache', $binding );

		return $docker_compose_yml;
	}
}
