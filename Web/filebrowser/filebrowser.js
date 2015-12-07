tinymce.PluginManager.add("filebrowser", function(editor){
  // save our original file browser callback
  editor.settings.origFileBrowserCallback = editor.settings.file_browser_callback;
  // hijack the file_browser_callback
  editor.settings.file_browser_callback = function(fieldName, url, type, win) {
    if (type === 'file') {
      // we only want to use our custom filebrowser for file selections and not media
      editor.windowManager.open({
        title: 'Choose a page',
        url: Gustavus.Utility.URL.urlify('/concert/filebrowser/filebrowser.php', {'filePath': Gustavus.Concert.filePath}),
        buttons: [{
            text: 'Concert File Manager',
            onclick: function() {
              editor.windowManager.close();
              return editor.settings.origFileBrowserCallback(fieldName, url, type, win);
            },
            icon: 'browse',
            classes: 'primary'
        }]
      }, {
        setURL: function(url) {
          // close the current window
          editor.windowManager.close();
          var item = win.document.getElementById(fieldName);
          item.value = editor.convertURL(url);
          // trigger a change event so it will update the text to display
          if ('createEvent' in document) {
            var event = document.createEvent('HTMLEvents');
            event.initEvent('change',!1,!0);
            item.dispatchEvent(event);
          } else {
            item.fireEvent('onchange');
          }
        }
      });
    } else if (editor.settings.origFileBrowserCallback) {
      // call the original file_browser_callback
      return editor.settings.origFileBrowserCallback(fieldName, url, type, win);
    }
  }
});