/*
 * note that some of the tasks defined here may not be used in EVERY project
 * I build.
 *
 * @todo figure out how to call `parcel` (to complile the blocks JS) from grunt
 *       so that it doesn't have to be called from npm.
 */

/**
 * Extract dependencies from package.json for use in a 'src:' property of a task
 *
 * @param {object} pkg The parsed package.json
 * @returns array
 *
 * @link https://stackoverflow.com/a/34629499/7751811
 */
function getDependencies( pkg ) {
	'use strict';

	if ( ! pkg.hasOwnProperty( 'dependencies' ) ) {
		return [];
	}

	return Object.keys( pkg.dependencies ).map( function( val ) {
		return 'node_modules/' + val + '/**';
	} );
}

module.exports = function( grunt ) {
	'use strict';

	var pkg = grunt.file.readJSON( 'package.json' );

	// Project configuration.
	grunt.initConfig( {
		pkg: pkg,

		// cleanup
		clean: {
			build: [
				'<%= pkg.name %>', '<%= pkg.name %>.zip', 'assets/**/*.min.*', 'assets/css/**/*-rtl.css'
			],
			release: [
				'<%= pkg.name %>'
			],
		},

		// minify JS files
		uglify: {
			build: {
				files: [
					{
						expand: true,
						src: [
							'assets/js/**/*.js', 'vendor/**/*.js',
							'!assets/js/**/*.min.js', '!vendor/**/*.min.js'
						],
						dest: '.',
						ext: '.min.js',
					}
				],
			},
		},

		// create RTL CSS files
		rtlcss: {
			options: {
				// borrowed from Core's Gruntfile.js, with a few mods
				// 1. reformated (e.g., [\n\t{ -> [ {, etc)
				// 2. dashicon content strings changed from '"\\f140"'
				//    to "'\\f140'", etc
				opts: {
					clean: false,
					processUrls: {
						atrule: true,
						decl: false,
					},
					stringMap: [
						{
							name: 'import-rtl-stylesheet',
							priority: 10,
							exclusive: true,
							search: ['.css'],
							replace: ['-rtl.css'],
							options: {
								scope: 'url',
								ignoreCase: false
							},
						},
					],
				},
				// @todo grunt-rtlcss appears to require leading tabs in order for the
				//       "plugin" to find the appropriate lines for change.  Look into
				//       whether there is a config option to change that, in case
				//       assets/css/wphelpkiticons.css gets committed with spaces.
				plugins: [
					{
						name: 'swap-dashicons-left-right-arrows',
						priority: 10,
						directives: {
							control: {},
							value: []
						},
						processors: [
							{
								expr: /content/im,
								action: function( prop, value ) {
									if ( value === "'\\f141'" ) { // dashicons-arrow-left
										value = "'\\f139'";
									}
									else if ( value === "'\\f340'" ) { // dashicons-arrow-left-alt
										value = "'\\f344'";
									}
									else if ( value === "'\\f341'" ) { // dashicons-arrow-left-alt2
										value = "'\\f345'";
									}
									else if ( value === "'\\f139'" ) { // dashicons-arrow-right
										value = "'\\f141'";
									}
									else if ( value === "'\\f344'" ) { // dashicons-arrow-right-alt
										value = "'\\f340'";
									}
									else if ( value === "'\\f345'" ) { // dashicons-arrow-right-alt2
										value = "'\\f341'";
									}

									return {
										prop: prop,
										value: value
									};
								},
							}
						],
					}
				],
			},
			build: {
				files: [
					{
						expand: true,
						src: [
							'assets/css/**/*.css', 'vendor/**/*.css',
							'!assets/css/**/*-rtl.css', '!assets/css/**/*.min.css',
							'!vendor/**/*-rtl.css', '!vendor/**/*.min.css'
							],
						dest: '.',
						ext: '-rtl.css',
					}
				],
			},
		},

		// SASS pre-process CSS files
		sass: {
			options: {
				style: 'expanded',
			},
			build: {
				files: [
					{
						expand: true,
						src: [
							'assets/css/**/*.scss'
						],
						dest: '.',
						ext: '.css',
					}
				],
			},
		},

		// minify CSS files
		cssmin: {
			build: {
				files: [
					{
						expand: true,
						src: [
							'assets/css/**/*.css', 'vendor/**/*.css',
							'!assets/css/**/*.min.css', '!vendor/**/*.min.css',
						],
						dest: '.',
						ext: '.min.css',
					}
				],
			},
		},

		// copy files from one place to another
		copy: {
			release: {
				expand: true,
				// installing the shc-framework composer dependency creates vendor/bin
				// for some reason even tho it has no binaries...make sure that dir isn't
				// included in the release.
				src: [
					'plugin.php', 'readme.txt', 'assets/**',
					'includes/**', 'utils/**', 'vendor/**',
					'!assets/css/**/*.scss',
					'!vendor/bin',
				],
				dest: '<%= pkg.name %>',
			},
			node_modules: {
				expand: true,
				src: getDependencies( pkg ),
				dest: 'vendor',
				rename: function( dest, src ) {
					return dest + '/' + src.substring( src.indexOf( '/' ) + 1 );
				},
			},
		},

		// package into a zip
		zip: {
			build: {
				expand: true,
				cwd: '.',
				src: '<%= pkg.name %>/**',
				dest: '<%= pkg.name %>.<%= pkg.version %>.zip',
			},
		},

		// do string search/replace on various files
		replace: {
			namespace: {
				src: [
					'plugin.php', 'uninstall.php', 'includes/**/*.php',
					'vendor/shc/**/*.php',
				],
				overwrite: true,
				replacements: [
					{
						from: /^namespace (.*);$/m,
						to: 'namespace <%= pkg.namespace %>;',
					}
				],
			},
			version_readme_txt: {
				src: ['readme.txt'],
				overwrite: true,
				replacements: [
					{
						from: /^(Stable tag:) (.*)/m,
						to: '$1 <%= pkg.version %>',
					},
				],
			},
			version_plugin: {
				src: ['plugin.php'],
				overwrite: true,
				replacements: [
					// this is for the plugin_data comment
					{
						from: /^( \* Version:) (.*)/mg,
						to: '$1 <%= pkg.version %>',
					},
					// this is for plugins that use a static class var
					// instead of the dynamic $this->version that SHC Framework allows
					{
						from: /^(.*static \$VERSION =) '(.*)'/m,
						to: "$1 '<%= pkg.version %>'",
					},
					// this is for plugins that use a class const
					// instead of the dynamic $this->version that SHC Framework allows
					{
						from: /^(.*const VERSION =) '(.*)'/m,
						to: "$1 '<%= pkg.version %>'",
					},
				],
			},
			plugin_uri: {
				src: ['plugin.php'],
				overwrite: true,
				replacements: [
					// this is for the plugin_uri comment
					{
						from: /^( \* Plugin URI:) (.*)/m,
						to: '$1 <%= pkg.repository %>/<%= pkg.name %>',
					},
					// this is for the github_plugin_uri comment
					{
						from: /^( \* GitHub Plugin URI:) (.*)/m,
						to: '$1 <%= pkg.repository %>/<%= pkg.name %>',
					},
				],
			},
			description_readme_txt: {
				src: ['readme.txt'],
				overwrite: true,
				replacements: [
					{
						// for this regex to work the readme.txt file MUST have
						// unix line endings (Windows won't work).
						// note the look ahead. Also, the repeat on the
						// newline char class MUST be {2,2}, using just {2} always
						// fails
						from: /.*(?=[\n\r]{2,2}== Description ==)/m,
						to: '<%= pkg.description %>',
					},
				],
			},
			description_plugin: {
				src: ['plugin.php'],
				overwrite: true,
				replacements: [
					{
						from: /^( \* Description:) (.*)/m,
						to: '$1 <%= pkg.description %>',
					},
				],
			},
			plugin_name_readme_txt: {
				src: ['readme.txt'],
				overwrite: true,
				replacements: [
					{
						from: /^=== (.*) ===/m,
						to: '=== <%= pkg.plugin_name %> ==='
					},
				],
			},
			plugin_name_plugin: {
				src: ['plugin.php'],
				overwrite: true,
				replacements: [
					{
						from: /^( \* Plugin Name:) (.*)/m,
						to: '$1 <%= pkg.plugin_name %>',
					},
				],
			},
			plugin_name_phpunit: {
				src: ['phpunit.xml.dist'],
				overwrite: true,
				replacements: [
					{
						from: /^(\s*<const name='PLUGIN_TEST_NAME' value=')([^']+)(' \/>)/m,
						to: '$1<%= pkg.name %>$3',
					},
				],
			},
			tested_up_to: {
				src: ['readme.txt'],
				overwrite: true,
				replacements: [
					{
						from: /^(Tested up to:) (.*)/m,
						to: '$1 <%= pkg.tested_up_to %>',
					},
				],
			},
			license_readme: {
				src: ['readme.txt'],
				overwrite: true,
				replacements: [
					{
						from: /^(License:) (.*)/m,
						to: '$1 <%= pkg.license %>',
					},
				],
			},
			license_uri_readme: {
				src: ['readme.txt'],
				overwrite: true,
				replacements: [
					{
						from: /^(License URI:) (.*)/m,
						to: '$1 <%= pkg.license_uri %>',
					},
				],
			},
			license_uri_plugin: {
				src: ['plugin.php'],
				overwrite: true,
				replacements: [
					{
						from: /^( \* License URI:) (.*)/m,
						to: '$1 <%= pkg.license_uri %>',
					},
				],
			},
			text_domain: {
				src: ['plugin.php'],
				overwrite: true,
				replacements: [
					// this is for the text domain comment
					{
						from: /^( \* Text Domain:) (.*)/m,
						to: '$1 <%= pkg.name %>',
					},
					// this is for __() and cousins
					{
						from: /<%= TextDomain %>/g,
						to: '<%= pkg.name %>',
					},
				],
			},
            composer_name: {
                src: ['composer.json'],
                overwrite: true,
                replacements: [
                	// this is for "name" : "plugin-name"
                    {
                        from: /^(\s*"name"\s*:\s*")(.*)"/m,
                        to: '$1<%= pkg.name %>"',
                    },
                ],
            },
		},

		// lint JS files
		jshint: {
			gruntfile: {
				options: {
					jshintrc: '.jshintrc'
				},
				src: 'Gruntfile.js'
			},
			assets: {
				options: {
					jshintrc: '.jshintrc'
				},
				// note: we do NOT jshint any of the blocks JS, src or dist.
				src: [
					'assets/**/*.js', '!assets/**/*.min.js',
					'!assets/js/blocks/**/*.js'
				]
			},
			tests: {
				options: {
					jshintrc: '.jshintrc'
				},
				src: [
					'tests/**/*.js',
					'!tests/**/*.min.js'
				]
			},
		},

		// watch for mods to certain files and fire appropriate tasks
		// when they change
		// note: I do NOT use this vary often
		watch: {
			css: {
				files: [
					'assets/css/**/*.scss'
				],
				tasks: ['sass'],
				options: {
					spawn: false,
				},
			},
			js: {
				files: [
					'assets/js/**/*.js',
					'!assets/js/**/*.min.js'
				],
				tasks: ['jshint'],
				options: {
					spawn: false,
				},
			},
		},

		// Create README.md for GitHub.
		wp_readme_to_markdown: {
			options: {
				screenshot_url: 'assets/images/{screenshot}.png?raw=true'
			},
			dest: {
				files: {
					'README.md': 'readme.txt'
				}
			},
//			post_convert: function( content ) {
//				content = content.replace( /^### [0-9]+..*$/g, '' );
//
//				return content;
//			}
		},

	} );

	grunt.loadNpmTasks( 'grunt-contrib-clean' );
	grunt.loadNpmTasks( 'grunt-composer' );
	grunt.loadNpmTasks( 'grunt-contrib-uglify-es' );
	grunt.loadNpmTasks( 'grunt-contrib-cssmin' );
	grunt.loadNpmTasks( 'grunt-contrib-jshint' );
	grunt.loadNpmTasks( 'grunt-contrib-copy' );
	grunt.loadNpmTasks( 'grunt-contrib-sass' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.loadNpmTasks( 'grunt-text-replace' );
	grunt.loadNpmTasks( 'grunt-rtlcss' );
	grunt.loadNpmTasks( 'grunt-wp-readme-to-markdown' );
	grunt.loadNpmTasks( 'grunt-zip' );

	grunt.registerTask( 'default', ['build'] );
	grunt.registerTask( 'build', [
		'clean', 'composer:dump-autoload:optimize', //'replace',
		'copy:node_modules', 'sass', 'rtlcss', 'cssmin', 'uglify'
	] );
	grunt.registerTask( 'autoload', ['composer:dump-autoload:optimize'] );

	grunt.registerTask( 'namespace', ['replace:namespace'] );
	grunt.registerTask( 'autoload', ['composer:dump-autoload:optimize'] );
	grunt.registerTask( 'update', ['composer:update', 'namespace', 'autoload'] );
	grunt.registerTask( 'init', ['replace', 'update'] );

	// @todo install/configure grunt-phpunit and add it to prerelease
	//       initial attempts to do so weren't successful.
	grunt.registerTask( 'prerelease', ['jshint'] );
	grunt.registerTask( 'readme', [
		'replace:version_readme_txt', 'replace:plugin_name_readme_txt',
		'replace:description_readme_txt',
		'replace:license_readme', 'replace:license_uri_readme',
		'replace:tested_up_to',
		'wp_readme_to_markdown'
	] );
	grunt.registerTask( 'release', [
		'build', 'prerelease', 'replace', 'wp_readme_to_markdown', 'copy', 'zip:build', 'clean:release'
	] );
};
