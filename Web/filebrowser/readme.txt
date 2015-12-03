We need to re-minify the js file whenever we make changes

java -jar /cis/lib/Gustavus/Resources/closure-compiler/compiler.jar --js_output_file /cis/lib/Gustavus/Concert/Web/filebrowser/filebrowser.min.js --language_in ECMASCRIPT5 --compilation_level SIMPLE --warning_level QUIET /cis/lib/Gustavus/Concert/Web/filebrowser/filebrowser.js