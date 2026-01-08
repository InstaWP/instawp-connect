const gulp = require('gulp'),
    del = require('del'),
    wpPot = require('gulp-wp-pot'),
    zip = require('gulp-zip');
const { series, parallel } = require('gulp');

// Pot Path
var potPath = ['./*.php', 'includes/*.php', 'includes/**/*.php', 'admin/*.php', 'admin/**/*.php', 'cli/*.php'];

// ZIP Path
var zipPath = [
    './',
    './**',
    './**',
    '!./.git/**',
    '!./**/.gitignore',
    '!./**/*.md',
    '!./**/*.scss',
    '!./**/tailwind-input.css',
    '!./**/composer.json',
    '!./**/tailwind.config.js',
    '!./**/auth.json',
    '!./**/.gitignore',
    '!./**/LICENSE',
    '!./**/phpunit*',
    '!./tests/**',
    '!./node_modules/**',
    '!./build/**',
    '!./gulpfile.js',
    '!./package.json',
    '!./package-lock.json',
    '!./composer.json',
    '!./composer.lock',
    '!./phpcs.xml',
    '!./LICENSE',
    '!./README.md',
    '!./CLAUDE.md',
    '!./vendor/bin/**',
    '!./vendor/**/*.txt',
    '!./includes/file-manager/instawp*.php',
    '!./includes/database-manager/instawp*.php',
];

// Clean CSS, JS and ZIP
function clean_files() {
    let cleanPath = ['../instawp-connect.zip'];
    return del(cleanPath, { force: true });
}

// Create POT file
function create_pot() {
    return gulp.src(potPath)
        .pipe(wpPot({
            domain: 'instawp-connect',
            package: 'InstaWP Connect',
            copyrightText: 'InstaWP',
            ignoreTemplateNameHeader: true
        }))
        .pipe(gulp.dest('languages/instawp-connect.pot'));
}

// Create ZIP file
function create_zip() {
    return gulp.src(zipPath, { base: '../' })
        .pipe(zip('instawp-connect.zip'))
        .pipe(gulp.dest('../'))
}

exports.default = series(clean_files, create_pot, create_zip);