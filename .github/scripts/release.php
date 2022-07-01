<?php

function line( $text = '' ) {
	echo $text, PHP_EOL;
}

function run( $command, &$result_code = null ) {
	line( $command );

	$last_line = system( $command, $result_code );

	line();

	return $last_line;
}

/**
 * MemberPress license key.
 */
$memberpress_license_key    = getenv( 'MEMBERPRESS_LICENSE_KEY' );
$memberpress_license_domain = getenv( 'MEMBERPRESS_LICENSE_DOMAIN' );

if ( empty( $memberpress_license_key ) ) {
	echo 'MemberPress license key not defined in `MEMBERPRESS_LICENSE_KEY` environment variable.';

	exit( 1 );
}

if ( empty( $memberpress_license_domain ) ) {
	echo 'MemberPress license key not defined in `MEMBERPRESS_LICENSE_DOMAIN` environment variable.';

	exit( 1 );
}

/**
 * Request info.
 */
line( '::group::Check MemberPress' );

$url = sprintf(
	'https://mothership.caseproof.com/versions/info/%s',
	$memberpress_license_key
);

$data = run(
	sprintf(
		'curl --data %s --request POST %s',
		escapeshellarg( 'domain=' . $memberpress_license_domain ),
		escapeshellarg( $url )
	)
);

$result = json_decode( $data );

if ( ! is_object( $result ) ) {
	throw new Exception(
		sprintf(
			'Unknow response from: %s.',
			$url 
		)
	);

	exit( 1 );
}

$version = $result->version;

$url = $result->url;

line(
	sprintf(
		'MemberPress Version: %s',
		$version
	)
);

line(
	sprintf(
		'MemberPress ZIP URL: %s',
		$url
	)
);

line( '::endgroup::' );

/**
 * Files.
 */
$work_dir = tempnam( sys_get_temp_dir(), '' );

unlink( $work_dir );

mkdir( $work_dir );

$archives_dir = $work_dir . '/archives';
$plugins_dir  = $work_dir . '/plugins';

mkdir( $archives_dir );
mkdir( $plugins_dir );

$plugin_dir = $plugins_dir . '/memberpress';

$zip_file = $archives_dir . '/memberpress-' . $version . '.zip';

/**
 * Download ZIP.
 */
line( '::group::Download MemberPress' );

run(
	sprintf(
		'curl %s --output %s',
		escapeshellarg( $result->url ),
		$zip_file
	)
);

line( '::endgroup::' );

/**
 * Unzip.
 */
line( '::group::Unzip MemberPress' );

run(
	sprintf(
		'unzip %s -d %s',
		escapeshellarg( $zip_file ),
		escapeshellarg( $plugins_dir )
	)
);

line( '::endgroup::' );

/**
 * Synchronize.
 * 
 * @link http://stackoverflow.com/a/14789400
 * @link http://askubuntu.com/a/476048
 */
line( '::group::Synchronize MemberPress' );

run(
	sprintf(
		'rsync --archive --delete-before --exclude=%s --exclude=%s --exclude=%s --verbose %s %s',
		escapeshellarg( '.git' ),
		escapeshellarg( '.github' ),
		escapeshellarg( 'composer.json' ),
		escapeshellarg( $plugin_dir . '/' ),
		escapeshellarg( '.' )
	)
);

line( '::endgroup::' );

/**
 * Git user.
 * 
 * @link https://github.com/roots/wordpress/blob/13ba8c17c80f5c832f29cf4c2960b11489949d5f/bin/update-repo.php#L62-L67
 */
run(
	sprintf(
		'git config user.email %s',
		escapeshellarg( 'info@memberpress.com' )
	)
);

run(
	sprintf(
		'git config user.name %s',
		escapeshellarg( 'MemberPress' )
	)
);

/**
 * Git commit.
 * 
 * @link https://git-scm.com/docs/git-commit
 */
run( 'git add --all' );

run(
	sprintf(
		'git commit --all -m %s',
		escapeshellarg(
			sprintf(
				'Updates to %s',
				$version
			)
		)
	)
);

run( 'git config --unset user.email' );
run( 'git config --unset user.name' );

run( 'gh auth status' );

run( 'git push origin main' );

/**
 * GitHub release view.
 */
$tag = 'v' . $version;

run(
	sprintf(
		'gh release view %s',
		$tag
	),
	$result_code
);

$release_not_found = ( 1 === $result_code );

/**
 * GitHub release.
 * 
 * @todo https://memberpress.com/wp-json/wp/v2/pages?slug=change-log
 * @link https://cli.github.com/manual/gh_release_create
 */
if ( $release_not_found ) {
	run(
		sprintf(
			'gh release create %s %s --title %s',
			$tag,
			$zip_file,
			$version
		)
	);
}
