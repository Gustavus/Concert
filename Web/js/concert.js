// Make sure our Gustavus pseudo namespace exists
if(!window.Gustavus) {
  window.Gustavus = {};
}

/**
 * Concert pseudo namespace
 * @author Billy Visto
 */
Gustavus.Concert = {
  /**
   * Base url for ajax requests
   * @type {String}
   */
  baseUrl: '/concert/',

  /**
   * File path of the page we are editing
   * @type {String}
   */
  filePath: '',

  /**
   * TinyMCE configuration
   * @type {Array}
   */
  tinyMceConfig: [
    {title: "Headers", items: [
      {title: "Header 1", format: "h1"},
      {title: "Header 2", format: "h2"},
      {title: "Header 3", format: "h3"},
      {title: "Header 4", format: "h4"},
      {title: "Header 5", format: "h5"},
      {title: "Header 6", format: "h6"}
    ]},
    {title: "Inline", items: [
      {title: "Bold", icon: "bold", format: "bold"},
      {title: "Italic", icon: "italic", format: "italic"},
      {title: "Underline", icon: "underline", format: "underline"},
      {title: "Strikethrough", icon: "strikethrough", format: "strikethrough"},
      {title: "Superscript", icon: "superscript", format: "superscript"},
      {title: "Subscript", icon: "subscript", format: "subscript"},
      {title: "Code", icon: "code", format: "code"},
      {title: 'Small', inline: 'small'}
    ]},
    {title: "Blocks", items: [
      {title: "Paragraph", format: "p"},
      {title: "Blockquote", format: "blockquote"},
      {title: "Div", format: "div"},
      {title: "Pre", format: "pre"}
    ]},
    {title: "Alignment", items: [
      {title: "Left", icon: "alignleft", format: "alignleft"},
      {title: "Center", icon: "aligncenter", format: "aligncenter"},
      {title: "Right", icon: "alignright", format: "alignright"},
      {title: "Justify", icon: "alignjustify", format: "alignjustify"}
    ]},
    {title: 'Classes', items: [
      {title: 'Disabled', selector: '*', classes: 'disabled'},
      {title: 'Message', selector: '*', inline : 'span', classes: 'message'},
      {title: 'Highlight', selector: '*', inline : 'span',  classes: 'highlight'},
      {title: 'Box', selector: '*', inline : 'span',  classes: 'box'},
      {title: 'Boxright', selector: '*', inline : 'span',  classes: 'boxright'},
      {title: 'Boxleft', selector: '*', inline : 'span',  classes: 'boxleft'},
      {title: 'Small', selector: '*', inline : 'span',  classes: 'small'},
      {title: 'Fancy', selector: 'table,img',  classes: 'fancy'},
      {title: 'Striped', selector: 'table', classes: 'striped'},
      {title: 'Sortable', selector: 'table', classes: 'sortable'},
      {title: 'Left', selector: 'img', classes: 'left'},
      {title: 'Right', selector: 'img', classes: 'right'},
    ]}
  ],

  /**
   * looks for any editable divs and sets up our wysiwyg editor on them
   * @return {undefined}
   */
  initilizeEditablePartsForEdits: function() {
    // @todo Remove this temporary flag
    var useCode = true;

    var plugins = [
      "advlist autolink lists link image charmap print preview anchor",
      "searchreplace visualblocks fullscreen",
      "insertdatetime media table contextmenu paste responsivefilemanager"//,
      //"spellchecker" http://www.tinymce.com/wiki.php/Plugin:spellchecker
    ];

    if (useCode) {
      plugins.push('code');
    }

    tinymce.init({
      // http://www.tinymce.com/wiki.php/Configuration
      selector: "div.editable",
      inline: true,
      plugins: plugins,
      convert_urls: false, // prevent messing with URLs
      allow_script_urls: false,
      relative_urls: false,
      forced_root_block : '',
      //invalid_elements http://www.tinymce.com/wiki.php/Configuration:invalid_elements
      //invalid_styles http://www.tinymce.com/wiki.php/Configuration:invalid_elements
      //keep_styles http://www.tinymce.com/wiki.php/Configuration:keep_styles
      menubar: 'edit insert view format table tools',
      toolbar: "insertfile undo redo | styleselect | bold italic | bullist numlist outdent indent | link image responsivefilemanager",

      image_advtab: true,
      external_filemanager_path: "/concert/filemanager/",
      filemanager_title:"Responsive Filemanager" ,
      external_plugins: { "filemanager" : "/concert/filemanager/plugin.min.js"},
      //toolbar: "insertfile undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image",
      // importcss_append: true,
      // importcss_merge_classes: true,
      // importcss_groups: true,
      // importcss_file_filter: '/templates/css/contribute-user.css',
      //visualblocks_default_state: true,
      //forced_root_block: false,
      extended_valid_elements: 'br',
      resize: false,
      table_class_list: [
        {title: 'None', value: ''},
        {title: 'Fancy', value: 'fancy'},
        {title: 'Striped', value: 'striped'},
        {title: 'Sortable', value: 'sortable'}
      ],
      //style_formats_merge: true,
      style_formats: Gustavus.Concert.tinyMceConfig,
      setup : function(editor) {
        var editableIsVisibleOnFocus = false;
        editor.on('focus', function(e) {
          editableIsVisibleOnFocus = $(editor.bodyElement).hasClass('show');
          $(editor.bodyElement).removeClass('show');
        });
        editor.on('blur', function(e) {
          // clean up content
          editor.setContent(Gustavus.Concert.mceCleanup(editor.getContent()), {format: 'raw'});
          //console.log(editor.getContent());
          if (editableIsVisibleOnFocus === true) {
            $(editor.bodyElement).addClass('show');
          }
        });
        //http://www.tinymce.com/wiki.php/api4:class.tinymce.ResizeEvent
        // editor.on('ObjectResized', function(e) {
        //   var $elem = $(e);
        //   console.log($elem.parent());
        //   if ($elem.parent().length < 1) {
        //     var width;
        //   } else {
        //     var parentWidth = $elem.parent().width();
        //     var elemWidth = $elem.width();
        //     if (elemWidth > parentWidth) {
        //       // not allowed
        //       var width = '100';
        //     } else {
        //       var width = parentWidth / elemWidth;
        //     }
        //   }
        //   if (width) {
        //     width += '%';
        //   }
        //   $elem.width(width);
        //   console.log('resize', width);
        // })

      }
      //theme_advanced_disable: ["code"],

      // menu : { // this is the complete default configuration
      //   file   : {title : 'File'  , items : 'newdocument'},
      //   edit   : {title : 'Edit'  , items : 'undo redo | cut copy paste pastetext | selectall'},
      //   insert : {title : 'Insert', items : 'link media | template hr'},
      //   view   : {title : 'View'  , items : 'visualaid'},
      //   format : {title : 'Format', items : 'bold italic underline strikethrough superscript subscript | formats | removeformat'},
      //   table  : {title : 'Table' , items : 'inserttable tableprops deletetable | cell row column'},
      //   tools  : {title : 'Tools' , items : 'spellchecker code'}
      // }
    });
    // console.log(tinymce.settings);
    //tinymce.settings.style_formats.push({title: 'Disabled', classes: 'disabled'});
  },

  // this will strip empty paragraphs
  mceCleanup: function(content) {
    var cleaned = content.replace(/<p>(?:[\s]|&nbsp;|<br[^>]*>)*<\/p>/g, '<br/>');
    return cleaned;
  },

  /**
   * Builds an object from the edited contents
   * @return {Object} Object of edits
   */
  buildEditsObject: function() {
    var edits = {};
    $('div.editable').each(function() {
      var $this = $(this);
      edits[$this.data('index')] = $this.html();
    });
    return edits;
  },

  /**
   * Sends a post request with the edited contents
   * @param {string} action Action we are saving for
   * @return {undefined}
   */
  saveEdits: function(action) {
    var edits = this.buildEditsObject();
    edits.concertAction = 'save';
    edits.saveAction = action;
    edits.filePath = this.filePath;
    // console.log(this.baseUrl);
    $.ajax({
      type: 'POST',
      //url: window.location.href,
      url : this.baseUrl,
      data: edits,
      dataType: 'json',
      success: function(data) {
        if (data && data.error) {
          alert(data.reason);
        } else {
          if (data && data.redirectUrl) {
            //window.location = data.redirectUrl;
          } else {
            console.log('Saved. Redirecting to: ' + Gustavus.Utility.URLUtil.urlify(Gustavus.Concert.redirectPath, {'concert': 'stopEditing'}));
            //window.location = Gustavus.Utility.URLUtil.urlify(Gustavus.Concert.redirectPath, {'concert': 'stopEditing'});
          }
        }
      },
      error: function() {
        // @todo add a failed message
        console.log('failed');
      }
    });
  },

  /**
   * Releases the lock for the current file
   * @return {jqXHR} jQuery XMLHttpRequest (jqXHR) object
   */
  releaseLock: function() {
    var data = {
      'concertAction': 'stopEditing',
      'filePath': this.filePath,
    };

    return $.ajax({
      type: 'POST',
      url : this.baseUrl,
      data: data,
      dataType: 'json',
    });
  },

  /**
   * Checks to see if the current file has a shared draft attached to the current user
   * @return {jqXHR} jQuery XMLHttpRequest (jqXHR) object
   */
  hasSharedDraft: function() {
    var data = {
      'concertAction': 'query',
      'query': 'hasSharedDraft',
      'filePath': this.filePath,
    }

    return $.ajax({
      type: 'POST',
      url : this.baseUrl,
      data: data,
      dataType: 'json',
    });
  },

  /**
   * Toggles showing what contents can be edited
   * @param  {jQuery} $button
   * @return {undefined}
   */
  toggleShowingEditableContent: function($button) {
    console.log($button.data('show'));
    if ($button.data('show') === true) {
      this.showEditableContent($button);
    } else {
      this.hideEditableContent($button);
    }
  },

  /**
   * Shows what contents can be edited
   * @param  {jQuery} $button
   * @return {undefined}
   */
  showEditableContent: function($button) {
    $('div.editable').each(function() {
      $(this).addClass('show');
    })
    $button.data('show', false);
    $button.html('Don\'t show editable areas');
  },

  /**
   * Hides showing what contents can be edited
   * @param  {jQuery} $button
   * @return {undefined}
   */
  hideEditableContent: function($button) {
    $('div.editable.show').each(function() {
      $(this).removeClass('show');
    })
    $button.data('show', true);
    $button.html('Show editable areas');
  }
};

$('#concertPublish').on('click', function(e) {
  e.preventDefault();
  Gustavus.Concert.saveEdits('publish');
})

$('#concertSavePrivateDraft').on('click', function(e) {
  e.preventDefault();
  var req = Gustavus.Concert.hasSharedDraft();
  req.done(function(data) {
    console.log(data);
    if (data) {
      $('#confirmPrivateDraft').dialog({
        modal: true,
        buttons: {
          'Save private draft': function() {
            Gustavus.Concert.saveEdits('savePrivateDraft');
            $(this).dialog('close');
          },
          Cancel: function() {
            $(this).dialog('close');
          }
        }
      });
    } else {
      Gustavus.Concert.saveEdits('savePrivateDraft');
    }
  })

  req.fail(function() {
    // something happened.
    alert('The draft was not successfully saved');
  })
})

$('#concertSavePublicDraft').on('click', function(e) {
  e.preventDefault();
  Gustavus.Concert.saveEdits('savePublicDraft');
})

$('#concertDiscardDraft').on('click', function(e) {
  e.preventDefault();
  Gustavus.Concert.saveEdits('discardDraft');
})

$('#concertStopEditing').on('click', function(e) {
    //e.preventDefault();
  var req = Gustavus.Concert.releaseLock();
  req.done(function(data) {
    if (!data) {
      e.preventDefault();
    }
  })
  req.fail(function() {
    e.preventDefault();
  })
})

$('#toggleShowingEditableContent').on('click', function(e) {
  e.preventDefault();
  Gustavus.Concert.toggleShowingEditableContent($(this));
})

/**
 * Document.ready()
 * @return {undefined}
 */
$(function() {
  Gustavus.Concert.initilizeEditablePartsForEdits();
  Gustavus.Concert.showEditableContent($('#toggleShowingEditableContent'));
});
