tinymce.PluginManager.add("filebrowser", function(editor){
  // save our original file browser callback
  editor.settings.origFilePickerCallback = editor.settings.file_picker_callback;
  // hijack the file_picker_callback
  editor.settings.file_picker_callback = function(callback, value, meta) {
    if (meta.filetype && meta.filetype === 'file') {
      // we only want to use our custom filebrowser for file selections and not media
      editor.windowManager.open({
        title: 'Choose a page',
        url: Gustavus.Utility.URL.urlify('/concert/filebrowser/filebrowser.php', {'filePath': Gustavus.Concert.filePath}),
        buttons: [{
            text: 'Concert File Manager',
            onclick: function() {
              editor.windowManager.close();
              return editor.settings.origFilePickerCallback(callback, value, meta);
            },
            icon: 'browse',
            classes: 'primary'
        }]
      }, {
        setURL: function(url) {
          // close the current window
          editor.windowManager.close();
          callback(editor.convertURL(url), {text: url});
        }
      });
    } else if (editor.settings.origFilePickerCallback) {
      // call the original file_picker_callback
      return editor.settings.origFilePickerCallback(callback, value, meta);
    }
  }
});