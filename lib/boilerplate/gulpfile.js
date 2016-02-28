var gulp = require('gulp');
var sass = require('gulp-sass');
var shell = require('gulp-shell');
var minifyCss = require('gulp-minify-css');
var browserSync = require('browser-sync');

var reload = browserSync.reload;

// -------------------------------------------------------------------------

gulp.task('sass', function () {
    gulp.src('./src/scss/**/*.scss')
        .pipe(sass().on('error', sass.logError))
        .pipe(minifyCss({ compatibility: 'ie9' }))
        .pipe(gulp.dest('assets/css'))
        .pipe(reload({stream:true}));
});

gulp.task('jspm-build', shell.task('jspm bundle-sfx main build.js --minify'));

gulp.task('browser-sync', function () {
    browserSync({
        proxy: '[_DOMAIN_]',
        notify: true,
        open: true
    });
});

// -------------------------------------------------------------------------

gulp.task('default', function () {
    gulp.watch('./src/scss/**/*.scss', ['sass']);
});

gulp.task('bs', ['sass', 'browser-sync'], function () {
    gulp.watch('./src/scss/**/*.scss', ['sass']);
});

gulp.task('build', function () {
    gulp.run('jspm-build');
});
