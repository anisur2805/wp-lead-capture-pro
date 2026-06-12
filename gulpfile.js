'use strict';

const { src, dest, watch, parallel, series } = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const uglify = require('gulp-uglify');
const rename = require('gulp-rename');
const sourcemaps = require('gulp-sourcemaps');

const paths = {
	scss: {
		src: 'assets/src/scss/frontend.scss',
		watch: 'assets/src/scss/**/*.scss',
		dest: 'assets/dist/css'
	},
	js: {
		src: 'assets/src/js/frontend.js',
		watch: 'assets/src/js/**/*.js',
		dest: 'assets/dist/js'
	}
};

function scssTask() {
	return src(paths.scss.src)
		.pipe(sourcemaps.init())
		.pipe(sass({ outputStyle: 'compressed' }).on('error', sass.logError))
		.pipe(rename({ suffix: '.min' }))
		.pipe(sourcemaps.write('.'))
		.pipe(dest(paths.scss.dest));
}

function jsTask() {
	return src(paths.js.src)
		.pipe(sourcemaps.init())
		.pipe(uglify())
		.pipe(rename({ suffix: '.min' }))
		.pipe(sourcemaps.write('.'))
		.pipe(dest(paths.js.dest));
}

function watchTask() {
	watch(paths.scss.watch, scssTask);
	watch(paths.js.watch, jsTask);
}

const build = parallel(scssTask, jsTask);

exports.scss = scssTask;
exports.js = jsTask;
exports.watch = watchTask;
exports.build = build;
exports.default = series(build, watchTask);
