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
   * Redirect path to use when redirecting
   * @type {String}
   */
  redirectPath: '',

  /**
   * TinyMCE menu configuration for default content
   * @type {Array}
   */
  tinyMceDefaultMenu: [
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
   * TinyMCE menu configuration for titles
   * @type {Array}
   */
  tinyMceTitleMenu: [
    {title: "Inline", items: [
      {title: "Bold", icon: "bold", format: "bold"},
      {title: "Italic", icon: "italic", format: "italic"},
      {title: "Superscript", icon: "superscript", format: "superscript"},
      {title: "Subscript", icon: "subscript", format: "subscript"},
    ]},
    {title: 'Classes', items: [
      {title: 'Small', selector: '*', inline : 'span',  classes: 'small'},
    ]}
  ],

  /**
   * TinyMCE menu configuration for site navs
   * @type {Array}
   */
  tinyMceSiteNavMenu: [
    {title: "Headings", items: [
      {title: "Heading", block: "h2", classes: "noFancyAmpersands"},
      {title: "Sub-heading", format: "h3"},
    ]},
    {title: "Inline", items: [
      {title: "Bold", icon: "bold", format: "bold"},
      {title: "Italic", icon: "italic", format: "italic"},
      {title: "Superscript", icon: "superscript", format: "superscript"},
      {title: "Subscript", icon: "subscript", format: "subscript"},
    ]},
    {title: 'Classes', items: [
      {title: 'Small', selector: '*', inline : 'span',  classes: 'small'},
    ]}
  ],

  /**
   * Default config for tinyMCE
   * @type {Object}
   */
  tinyMCEDefaultConfig: {
    // http://www.tinymce.com/wiki.php/Configuration
    //selector: null, // this must be set by the configuration builder.
    //plugins: null, // this must be set by the configuration builder.
    //style_formats_merge: true,
    //style_formats: null, // this must be set by the configuration builder.
    inline: true,
    convert_urls: false, // prevent messing with URLs
    allow_script_urls: false,
    relative_urls: false,
    forced_root_block : '',
    //invalid_elements http://www.tinymce.com/wiki.php/Configuration:invalid_elements
    //invalid_styles http://www.tinymce.com/wiki.php/Configuration:invalid_elements
    //keep_styles http://www.tinymce.com/wiki.php/Configuration:keep_styles
    menubar: 'edit insert view format table tools',
    toolbar: "insertfile undo redo | styleselect | bold italic | bullist numlist | link image responsivefilemanager | spellchecker",

    // media
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

    },
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

    // spellchecker
    spellchecker_language: 'en',
    spellchecker_rpc_url: 'https://beta.gac.edu/concert/spellchecker/spellchecker.php',
    spellchecker_languages: 'English=en'
    //spellchecker_languages: 'English=en,Danish=da,Dutch=nl,Finnish=fi,French=fr_FR, German=de,Italian=it,Polish=pl,Portuguese=pt_BR,Spanish=es,Swedish=sv'
  },

  /**
   * Builds the default configuration for tinyMCE
   * @return {Object} Configuration object built off of this.tinyMCEDefaultConfig
   */
  buildDefaultTinyMCEConfig: function() {
    var config = this.tinyMCEDefaultConfig;

    var plugins = [
      "advlist autolink lists link image charmap print preview anchor",
      "searchreplace visualblocks fullscreen",
      "insertdatetime media table contextmenu paste responsivefilemanager",
      "spellchecker" //http://www.tinymce.com/wiki.php/Plugin:spellchecker
    ];

    if (this.allowCode || this.isAdmin) {
      plugins.push('code');
    }

    config.plugins  = plugins;
    config.selector = "div.editable.default";

    config.style_formats = this.tinyMceDefaultMenu;

    return config;
  },

  /**
   * Builds the configuration for tinyMCE for editing titles
   * @return {Object} Configuration object built off of this.tinyMCEDefaultConfig
   */
  buildTitleTinyMCEConfig: function() {
    var config = this.tinyMCEDefaultConfig;

    var plugins = [
      "autolink link searchreplace visualblocks contextmenu paste",
      "spellchecker"
    ];

    if (this.isAdmin) {
      plugins.push('code');
    }

    config.plugins  = plugins;
    config.selector = "div.editable.title";

    config.style_formats = this.tinyMceTitleMenu;

    config.toolbar = "undo redo | styleselect | bold italic | link | spellchecker";

    return config;
  },

  /**
   * Builds the configuration for tinyMCE for editing titles
   * @return {Object} Configuration object built off of this.tinyMCEDefaultConfig
   */
  buildSiteNavTinyMCEConfig: function() {
    var config = this.tinyMCEDefaultConfig;

    var plugins = [
      "autolink lists link searchreplace visualblocks contextmenu paste",
      "spellchecker"
    ];

    if (this.isAdmin) {
      plugins.push('code');
    }

    config.plugins  = plugins;
    config.selector = "div.editable.siteNav";

    config.style_formats = this.tinyMceSiteNavMenu;
    // add class noFancyAmpersands to h2 and h3 elements that get inserted
    config.formats = {
      h2: {block: 'h2', 'classes': 'noFancyAmpersands'},
      h3: {block: 'h3', 'classes': 'noFancyAmpersands'}
    }

    config.toolbar = "undo redo | styleselect | bold italic | bullist | link | spellchecker";

    return config;
  },

  /**
   * looks for any editable divs and sets up our wysiwyg editor on them
   * @return {undefined}
   */
  initilizeEditablePartsForEdits: function() {
    // add tinyMCE to default editable areas
    tinymce.init(this.buildDefaultTinyMCEConfig());
    // add tinyMCE to page titles
    tinymce.init(this.buildTitleTinyMCEConfig());
    // add tinyMCE to the site nav
    tinymce.init(this.buildSiteNavTinyMCEConfig());
  },

  /**
   * Cleanup function for tinyMCE to strip empty paragraphs
   * @param  {String} content Content to clean up
   * @return {String} Cleaned content
   */
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
   * @param {String} action Action we are saving for
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
  },

  /**
   * Initializes concert
   * @return {undefined}
   */
  init: function() {
    /**
     * Document.ready()
     * @return {undefined}
     */
    $(function() {
      // add a class to define default tinyMCE settings
      $('div.editable').addClass('default');
      // mark titles and sub titles so they get a different tinyMCE configuration
      $('#page-titles div.editable').removeClass('default').addClass('title');
      // same with local navigation
      $('#local-navigation div.editable').removeClass('default').addClass('siteNav');

      Gustavus.Concert.initilizeEditablePartsForEdits();
      Gustavus.Concert.initilizeEditablePartsForEdits = null;
      Gustavus.Concert.showEditableContent($('#toggleShowingEditableContent'));
    });
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
  // @todo redirect to a url with concert=stopEditing
})

$('#toggleShowingEditableContent').on('click', function(e) {
  e.preventDefault();
  Gustavus.Concert.toggleShowingEditableContent($(this));
})