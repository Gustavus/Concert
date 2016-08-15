tinymce.PluginManager.add("filebrowser", function(editor) {
  /**
   * file browser function to use if we are using the file_browser_callback setting
   *
   * @param  {String} fieldName
   * @param  {String} url
   * @param  {String} type
   * @param  {Object} win
   * @return {undefined}
   */
  var fileBrowser = function(fieldName, url, type, win) {
    if (type === 'file') {
      // we only want to use our custom filebrowser for file selections and not media
      editor.windowManager.open({
        title: 'Choose a page',
        url: Gustavus.Utility.URL.urlify('/concert/filebrowser/filebrowser.php', {'filePath': Gustavus.Concert.filePath}),
        buttons: [{
            text: 'Concert File Manager',
            onclick: function() {
              editor.windowManager.close();
              return tinymce.activeEditor.settings.origFileBrowserCallback(fieldName, url, type, win);
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
    } else if (tinymce.activeEditor.settings.origFileBrowserCallback) {
      // call the original file_browser_callback
      return tinymce.activeEditor.settings.origFileBrowserCallback(fieldName, url, type, win);
    }
  };

  /**
   * File browser function to use if we are using the file_picker_callback setting.
   *
   * @param  {Function} callback callback that will update the link
   * @param  {String|Null}   value
   * @param  {Object}   meta
   * @return {undefined}
   */
  var filePicker = function(callback, value, meta) {
    if (meta.filetype && meta.filetype === 'file') {
      // we only want to use our custom filebrowser for file selections and not media
      editor.windowManager.open({
        title: 'Choose a page',
        url: Gustavus.Utility.URL.urlify('/concert/filebrowser/filebrowser.php', {'filePath': Gustavus.Concert.filePath}),
        buttons: [{
          text: 'Concert File Manager',
          onclick: function() {
            editor.windowManager.close();
            return tinymce.activeEditor.settings.origFilePickerCallback(callback, value, meta);
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
    } else if (tinymce.activeEditor.settings.origFilePickerCallback) {
      //open responsiveFilemanager
      return tinymce.activeEditor.settings.origFilePickerCallback(callback, value, meta);
    }
  };

  if (tinymce.activeEditor.settings.file_picker_callback) {
    // we have a file_picker_callback. Hijack it so we can use our custom picker
    tinymce.activeEditor.settings.origFilePickerCallback = tinymce.activeEditor.settings.file_picker_callback;
    tinymce.activeEditor.settings.file_picker_callback = filePicker;
  } else if (tinymce.activeEditor.settings.file_browser_callback) {
    // we have a file_browser_callback. Hijack it so we can use our custom browser
    tinymce.activeEditor.settings.origFileBrowserCallback = tinymce.activeEditor.settings.file_browser_callback;
    tinymce.activeEditor.settings.file_browser_callback = fileBrowser;
  }
});