/**
 * @license Copyright 2011-2014 BitPay Inc., MIT License 
 * see https://github.com/bitpay/woocommerce-bitpay/blob/master/LICENSE
 */

'use strict';

module.exports = function(grunt) {

  // Project configuration.
  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    clean: {
      build: ['dist'],
      dev: {
        src: ['/var/www/wp-content/plugins/woocommerce-bitpay/'],
        options: {
          force: true
        }
      }
    },
    copy: {
      build: {
        files: [
          {
            expand: true,
            cwd: 'src/',
            src: ['**/**.php', 'assets/js/**/**.*', 'assets/img/**/**.*', 'templates/**/**.*'],
            dest: 'dist/'
          },
          {
            expand: true,
            src: ['vendor/**/**.*'],
            dest: 'dist/'
          }
        ]
      },
      dev: {
        files: [{
          expand: true,
          cwd: 'dist/',
          src: ['**/**'],
          dest: '/var/www/wp-content/plugins/woocommerce-bitpay/'
        }]
      }
    },
    cssmin: {
      build: {
        options: {
          banner: '/**\n * @license Copyright 2011-2014 BitPay Inc., MIT License\n * see https://github.com/bitpay/woocommerce-bitpay/blob/master/LICENSE\n */'
        },
        files: {
          'dist/assets/css/style.css': ['src/assets/css/**.css']
        }
      }
    },
    phpcsfixer: {
        build: {
            dir: 'src/'
        },
        options: {
            bin: 'vendor/bin/php-cs-fixer',
            diff: true,
            ignoreExitCode: true,
            level: 'all',
            quiet: true
        }
    },
    watch: {
      scripts: {
        files: ['src/**/**.*'],
        tasks: ['dev'],
        options: {
          spawn: false,
          atBegin: true
        },
      },
    },
  });

  // Load the plugins
  grunt.loadNpmTasks('grunt-contrib-clean');
  grunt.loadNpmTasks('grunt-contrib-copy');
  grunt.loadNpmTasks('grunt-contrib-cssmin');
  grunt.loadNpmTasks('grunt-contrib-symlink');
  grunt.loadNpmTasks('grunt-contrib-watch');
  grunt.loadNpmTasks('grunt-php-cs-fixer');

  // Default task(s).
  grunt.registerTask('build', ['phpcsfixer', 'clean:build', 'cssmin:build', 'copy:build']);
  grunt.registerTask('dev', ['build', 'clean:dev', 'copy:dev']);
  grunt.registerTask('default', 'build');

};
