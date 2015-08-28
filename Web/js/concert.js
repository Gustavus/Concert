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
  baseUrl: '/concert/mosh/',

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
   * Flag to ignore dirty editors
   * @type {Boolean}
   */
  ignoreDirtyEditors: false,

  /**
   * Selector of elements that get dynamically hidden via template javascript
   * @type {String}
   */
  hiddenElementSelector: 'div.editable .JSHide, div.editable .doShow, div.editable .doHide, div.editable .doToggle, div.editable .doFadeToggle, div.editable .toggleLink',

  /**
   * TinyMCE menu configuration for default content
   * @type {Array}
   */
  tinyMceDefaultMenu: [
    {title: "Headings", items: [
      {title: "Heading 1", format: "h2"},
      {title: "Heading 2", format: "h3"},
      {title: "Heading 3", format: "h4"},
      {title: "Heading 4", format: "h5"},
      {title: "Heading 5", format: "h6"}
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
      {title: 'Message', selector: 'p,div', classes: 'message'},
      {title: 'Highlight', selector: 'p,div', classes: 'highlight'},
      {title: 'Small', selector: '*', inline : 'span',  classes: 'small'},
      {title: 'Remove Sorting', selector: 'table', classes: 'nosort'},
      {title: 'Fancy', selector: 'img', classes: 'fancy'},
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

    forced_root_block: '',
    element_format: 'xhtml',
    //force_p_newlines: true, deprecated
    invalid_elements: 'script',
    // allow i, all attrs in spans (pullquotes), and remove empty li's and ul's
    extended_valid_elements: 'i[class],span[*],-li[*],-ul[*]',
    // ensure anchors can contain headings.
    valid_children: '+a[h2|h3|h4|h5|h6]',
    // we don't want it to convert rgb to hex.
    force_hex_style_colors: false,
    // we don't want to keep our styles when we hit return/enter.
    keep_styles: false,
    // disable indenting/outdenting things with padding. (Only allows nesting lists, and other elements.)
    indentation: false,

    //invalid_styles http://www.tinymce.com/wiki.php/Configuration:invalid_elements
    //keep_styles http://www.tinymce.com/wiki.php/Configuration:keep_styles
    menubar: 'edit insert view format table tools',
    toolbar: "insertfile undo redo | styleselect | bold italic | bullist numlist | link anchor image responsivefilemanager | spellchecker",
    // right click menu
    contextmenu: "link image inserttable | cell row column deletetable | list spellchecker",
    // set our customized menu.
    menu : {
      file   : {title : 'File'  , items : 'newdocument'},
      edit   : {title : 'Edit'  , items : 'undo redo | searchreplace | cut copy paste pastetext | selectall'},
      insert : {title : 'Insert', items : 'link media | template hr'},
      view   : {title : 'View'  , items : 'visualaid'},
      format : {title : 'Styles', items : 'bold italic underline strikethrough superscript subscript | list formats | clearformat'},
      table  : {title : 'Table' , items : 'inserttable tableprops deletetable | cell row column'},
      tools  : {title : 'Tools' , items : 'spellchecker code'}
    },
    // menu : { // this is the complete default configuration
    //   file   : {title : 'File'  , items : 'newdocument'},
    //   edit   : {title : 'Edit'  , items : 'undo redo | cut copy paste pastetext | selectall'},
    //   insert : {title : 'Insert', items : 'link media | template hr'},
    //   view   : {title : 'View'  , items : 'visualaid'},
    //   format : {title : 'Format', items : 'bold italic underline strikethrough superscript subscript | formats | removefo
    //   table  : {title : 'Table' , items : 'inserttable tableprops deletetable | cell row column'},
    //   tools  : {title : 'Tools' , items : 'spellchecker code'}
    // }

    // media
    image_advtab: false,
    // disable our class list since this overrides any other classes applied to images
    image_class_list: false,
    filemanager_title: "Concert File Manager" ,
    external_plugins: {"filemanager" : "/concert/filemanager/plugin.min.js"},

    resize: false,
    // @todo. What to do here. Default this to true? I don't think so. it might be something we can add a setting for later.
    //visualblocks_default_state: true,
    // table configuration
    // disable our class list since this overrides any other classes applied to tables
    table_class_list: false,
    table_advtab: false,
    table_cell_advtab: false,
    table_row_advtab: false,
    table_default_styles: {
      // this makes the table a lot easier to edit.
      width: '100%'
    },
    table_tab_navigation: true,

    setup : function(editor) {
      editor.on('blur', function(e) {
        // destroy any plugins that we want to be re-enabled when apply gets called
        Gustavus.Concert.destroyTemplatePluginsPreApply(editor.getElement());
        // run any filters added to 'page' in case a filter adds styles to any elements. (fancy tables)
        Extend.apply('page', editor.getElement());

        Gustavus.Concert.ignoreDirtyEditors = false;
        // clean up content
        var content = Gustavus.Concert.mceCleanup(editor.getContent());

        // convert images into GIMLI URLs.
        content = Gustavus.Concert.convertImageURLsToGIMLI(content);

        editor.setContent(content);
      });

      // add a shortcut to indent list elements
      // supports alt+= and alt+shift+=, but we broadcast it as alt++
      // 187 = "=/+" key
      // 107 = "+" numpad key
      editor.addShortcut('alt+187,alt+shift+187,alt+107', 'indent', 'Indent', this);
      // add a shortcut to outdent list elements
      // 189 = "-/_" key
      // 109 = "-" numpad key
      editor.addShortcut('alt+189,alt+109', 'outdent', 'Outdent', this);
      // replace Clear formatting button with Clear styles
      editor.addMenuItem('clearformat', {
        text: 'Clear styles',
        icon: 'removeformat',
        cmd: 'RemoveFormat'
      });

      // build our custom menu for lists
      editor.addMenuItem('list', {
        text: 'List',
        icon: false,
        menu: [
          {
            text: 'Bullet list',
            icon: 'bullist',
            onclick: function() {
              editor.execCommand('InsertUnorderedList');
            }
          },
          {
            text: 'Numbered list',
            icon: 'numlist',
            onclick: function() {
              editor.execCommand('InsertOrderedList');
            }
          },
          {
            text: '|'
          },
          {
            text: 'Increase indent',
            icon: 'indent',
            shortcut: 'Tab | Alt++',
            onclick: function() {
              editor.execCommand('Indent');
            }
          },
          {
            text: 'Decrease indent',
            icon: 'outdent',
            shortcut: 'Shift+Tab | Alt+-',
            onclick: function() {
              editor.execCommand('Outdent');
            }
          },
        ]
      });
    },

    // spellchecker
    spellchecker_language: 'en',
    spellchecker_rpc_url: '/js/tinymce-spellchecker/spellchecker.php',
    spellchecker_languages: 'English=en'
    //spellchecker_languages: 'English=en,Danish=da,Dutch=nl,Finnish=fi,French=fr_FR, German=de,Italian=it,Polish=pl,Portuguese=pt_BR,Spanish=es,Swedish=sv'
  },

  /**
   * Builds the default configuration for tinyMCE
   * @return {Object} Configuration object built off of this.tinyMCEDefaultConfig
   */
  buildDefaultTinyMCEConfig: function() {
    // clone our default config so we aren't modifying the default
    var config = $.extend(true, {}, this.tinyMCEDefaultConfig);

    var plugins = [
      "advlist autolink lists link image charmap print anchor",
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
    config.forced_root_block = 'p';

    return config;
  },

  /**
   * Builds the configuration for tinyMCE for editing titles
   * @return {Object} Configuration object built off of this.tinyMCEDefaultConfig
   */
  buildTitleTinyMCEConfig: function() {
    // clone our default config so we aren't modifying the default
    var config = $.extend(true, {}, this.tinyMCEDefaultConfig);

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
    // clone our default config so we aren't modifying the default
    var config = $.extend(true, {}, this.tinyMCEDefaultConfig);

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
    // remove any br tags that start the content
    cleaned = cleaned.replace(/^[\h|\v]*?<br\/>/, '');
    return cleaned;
  },

  /**
   * Cleanup function for editable content to strip editing leftovers
   * @param  {String} content Content to clean up
   * @param  {Boolean} isSiteNav Whether we are cleaning content for a site nav or not
   * @return {String} Cleaned content
   */
  cleanUpContent: function(content, isSiteNav) {
    var cleaned = Gustavus.Concert.mceCleanup(content);
    cleaned = cleaned.replace(/<br.data-mce[^>]*>/g, '');
    // get rid of mce-item* stuff that tinymce doesn't always clean up.
    cleaned = cleaned.replace(/ ?mce-item[^>" ]*/g, '');
    // get rid of any empty classes we may have.
    cleaned = cleaned.replace(/class=""/g, '');

    // Clean up tables
    var $content = $('<div>' + cleaned + '</div>');
    var $tables = $content.find('table');

    var removeFootableClasses = function($this, isTableElement) {
      if (isTableElement && $this.hasClass('footable')) {
        // remove old classes and classes that footable adds
        $this.removeClass('phone tablet default breakpoint fancy sortable');
      }
      var className = $this.attr('class');
      if (className) {
        className = className.replace(/[\s]?footable[\S]*/g, '').replace(/^\s/, '').replace(/\s\s+/g, ' ');
        if (className) {
          $this.attr('class', className);
        } else {
          $this.removeAttr('class');
        }
      }
    };

    $tables.each(function() {
      var $table = $(this);
      removeFootableClasses($table, true);
      $table.find('*').each(function() {
        // remove footable stuff from all elements within the table
        removeFootableClasses($(this));
      });
      // now that our classes have been removed, there will probably be a bunch of empty spans
      $table.find('span:empty').remove();
      var $trimmed = $table.find('[data-hide][data-footable-trimmed]');
      if ($trimmed) {
        // remove stuff added in when trimming tables
        $trimmed.removeAttr('data-hide data-footable-trimmed').css('display', '');
        $table.find('td[style="display: none;"]').css('display', '');
      }
      // remove anything else footable adds. (thead and possibly more)
      $table.find('[data-footable-added]').remove();
    });
    cleaned = $content.html();

    if (isSiteNav) {
      // clean up site nav stuff.
      $content = $('<div>' + content + '</div>');
      // the template adds spans with classes of text, description, etc.
      // The original link text will live in span.text, so just look for that and reset the link's html.
      $textSpans = $content.find('a span.text');

      $textSpans.each(function() {
        var $span = $(this);
        if ($span.find('span.text').length > 0) {
          // we want to find the inner most span.text
          return;
        }
        $span.parents('a').html($span.html());
      });
      cleaned = $content.html();
    }

    return cleaned;
  },

  /**
   * Function for tinyMCE to convert image urls into GIMLI urls
   * @param  {String} content Content to convert URLs for
   * @return {String} Adjusted content
   */
  convertImageURLsToGIMLI: function(content) {
    // wrap everything in a div so we only have one jquery object to work with
    var $content = $('<div>' + content + '</div>');

    $content.find('img').each(function() {
      $this = $(this);
      var width = $this.attr('width');
      var height = $this.attr('height');
      var src = $this.attr('src');

      var url = Gustavus.Utility.URL.parseURL(src);
      if (url.host && url.pathname && Gustavus.Utility.URL.isGustavusHost(url.host)) {
        if (url.pathname.indexOf('/slir/') === 0) {
          // we have a slir request. We want this converted to gimli.
          url.pathname = url.pathname.replace('/slir/', '/gimli/');
        }
        if (url.pathname.indexOf('/gimli/') === 0) {
          // we have a gimli url
          // we may need to update the width and height
          var widthMatches = url.pathname.match('^/gimli/[^w]*?(w[,.:x]?([0-9]+))');
          var currentWidth = widthMatches ? widthMatches[2] : null;

          var heightMatches = url.pathname.match('^/gimli/[^h]*?(h[,.:x]?([0-9]+))');
          var currentHeight = heightMatches ? heightMatches[2] : null;
          if (currentWidth == width && currentHeight == height) {
            // nothing to do
            return true;
          }
          // now we need to adjust the gimli parameters
          if (currentWidth) {
            if (!width) {
              width = currentWidth;
            }
            url.pathname = url.pathname.replace(widthMatches[1], 'w' + width);
          }
          if (currentHeight) {
            if (!height) {
              height = currentHeight;
            }
            url.pathname = url.pathname.replace(heightMatches[1], 'h' + height);
          }
          $this.attr('src', Gustavus.Utility.URL.buildURL(url));
          return true;
        }
        // we don't yet have a gimli url. Let's build one.
        var newPathname = '/gimli/';
        var separator = '';
        if (width) {
          newPathname += 'w' + width;
          separator = '-';
        }
        if (height) {
          newPathname += separator + 'h' + height;
        }
        newPathname += url.pathname;
        url.pathname = newPathname;
        $this.attr('src', Gustavus.Utility.URL.buildURL(url));
      }
    });

    return $content.html();
  },

  /**
   * Builds an object from the edited contents
   * @return {Object} Object of edits
   */
  buildEditsObject: function() {
    // remove template stuff
    Gustavus.Concert.destroyTemplatePluginsPreApply();
    Gustavus.Concert.destroyTemplatePluginsPostApply();
    $(Gustavus.Concert.hiddenElementSelector).each(function() {
      // remove display style from these elements that we forcefully displayed earlier
      $(this).css('display', '');
    });

    $('p.concertInsertion').each(function() {
      // we need to check if we need to remove our inserted paragraphs
      var $this = $(this);
      if ($this.html() === '&nbsp;' || $this.html() === '') {
        // empty or default. Need to remove it
        $this.remove();
      } else {
        // user has added to this. We just want to remove our concertInsertion class
        $this.removeClass('concertInsertion');
      }
    });

    var edits = {};
    for (var i in tinymce.editors) {
      if (tinymce.editors[i].isDirty()) {
        var $element = $(tinymce.editors[i].getElement());
        var isSiteNav = (tinymce.editors[i].settings.selector === 'div.editable.siteNav');
        edits[$element.data('index')] = Gustavus.Concert.cleanUpContent(tinymce.editors[i].getContent(), isSiteNav);
      }
    }
    return edits;
  },

  /**
   * Sends a post request with the edited contents
   * @param {String} action Action we are saving for
   * @param {Boolean} [allowRedirects=true] Whether or not to redirect on save
   * @param {Boolean} [redirectAfterTimeout=false] Whether to redirect after a timeout or just redirect
   * @param {jQuery} $element Element that triggered saving so we can re-enable it if needed
   * @return {undefined}
   */
  saveEdits: function(action, allowRedirects, redirectAfterTimeout, $element) {
    $('body').addClass('loading');
    if (allowRedirects == undefined) {
      allowRedirects = true;
    }
    this.ignoreDirtyEditors = true;
    var edits = this.buildEditsObject();

    edits.concertAction = 'save';
    edits.saveAction = action;
    edits.filePath = this.filePath;
    if (this.isCreation && this.fromFilePath) {
      edits.fromFilePath = this.fromFilePath;
    }
    $.ajax({
      type: 'POST',
      url : this.baseUrl,
      data: edits,
      dataType: 'json',
      success: function(data) {
        if (data && data.error) {
          $('body').removeClass('loading');
          alert(data.reason);
        } else if (allowRedirects) {
          if (data && data.redirectUrl) {
            var redirectUrl = data.redirectUrl;
          } else if (action === 'discardDraft') {
            var redirectUrl = window.location.toString();
          } else {
            var redirectUrl = Gustavus.Utility.URL.urlify(Gustavus.Concert.redirectPath, {'concert': 'stopEditing'});
          }
          if (redirectAfterTimeout) {
            setTimeout(function() {
              window.location = redirectUrl;
            }, 2000);
          } else {
            window.location = redirectUrl;
          }
        } else {
          $('body').removeClass('loading');
          Gustavus.Concert.ignoreDirtyEditors = false;
          if ($element) {
            $element.attr('disabled', '');
          }
        }
      },
      error: function() {
        $('body').removeClass('loading');
        Gustavus.Concert.ignoreDirtyEditors = false;
        if ($element) {
          $element.attr('disabled', '');
        }
        // @todo add a failed message.
        // Is this resolved? Should this be a jquery dialog? Or a colorbox window?
        alert('Something unexpected happened. Please try again. If your problem persists, please email <a href="mailto:web@gustavus.edu">web@gustavus.edu</a> with details on what is going on.');
      }
    });
  },

  /**
   * Releases the lock for the current file
   * @param  {Boolean} [async=true] Make the request execute synchronously. If this is false then the jqXHR will not contain jQuery.Deferred methods.
   * @return {jqXHR} jQuery XMLHttpRequest (jqXHR) object
   */
  releaseLock: function(async) {

    if (async === undefined || async === null) {
      async = true;
    } else if (typeof async !== 'boolean') {
      async = Boolean(async);
    }

    var data = {
      'concertAction': ((this.isSiteNavRequest) ? 'stopEditingSiteNav' : 'stopEditing'),
      'filePath': this.filePath,
    };

    return $.ajax({
      async: async,
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
   * Checks to see if the any of the tinymce editors are dirty
   * @return {Boolean}
   */
  hasDirtyEditor: function() {
    var dirtyEditor = false
    for (i in tinymce.editors) {
      if (tinymce.editors[i].isDirty()) {
        dirtyEditor = true;
        break;
      }
    }
    return dirtyEditor;
  },

  /**
   * Re-initializes template plugins
   *
   * @param  {HTMLElement} currObj object we need to reinit for
   * @return {undefined}
   */
  reInitTemplatePlugins: function(currObj) {
    // trigger load for jcaption
    $('img.autocaption', currObj).trigger('load');
  },

  /**
   * Destroys any plugins the template adds before Extend.apply gets called
   * @param  {HTMLElement} currObj object we need destroy plugins in
   * @return {undefined}
   */
  destroyTemplatePluginsPreApply: function(currObj) {
    // remove any jcaption stuff
    $('img.autocaption', currObj).each(function() {
      var parents = $(this).parents('.caption');
      var img = this;
      // make sure the image's title is the same as the caption in case someone attempted to edit it.
      var title = $(parents.first()).find('p').html();
      $(img).attr('title', title);
      var parent = parents.last()[0];
      if (parent) {
        parent.parentNode.innerHTML = parent.parentNode.innerHTML.replace(parent.outerHTML, img.outerHTML);
      }
    });

    $('aside.pullquote', currObj).each(function() {
      $pullquoteInsertion = $(this);
      // we aren't allowing people to edit pullquotes, so we won't even try to save them
      // var pullquoteText = $pullquoteInsertion.find('.q.cited').html();
      // pullquoteText = pullquoteText.substring(1, pullquoteText.length - 2).replace(/(\s[^\s]+)&nbsp;([^\s]+\s*)$/g, '$1 $2');
      // var cite = $pullquoteInsertion.find('cite').html();
      // cite = cite.substring(1);

      // var $pullquote = $pullquoteInsertion.next().find('span.pullquote').first();
      // $pullquote.attr('rel', cite);
      // $pullquote.html(pullquoteText);

      $pullquoteInsertion.remove();
    });
  },

  /**
   * Destroys any plugins the template adds after Extend.apply gets called
   * @param  {HTMLElement} currObj object we need destroy plugins in
   * @return {undefined}
   */
  destroyTemplatePluginsPostApply: function(currObj) {
    // Remove html that gets added in when toggling links
    var $toggleLinks = $('div.editable a.toggleLink', currObj);
    $toggleLinks.each(function() {
      $toggleLink = $(this);
      $toggleLink.removeClass('toggledOpen');
      var $toggleLinkRel = $($toggleLink.attr('rel'));
      $toggleLinkRel.hide();
      if ($toggleLinkRel.attr('style')) {
        $toggleLinkRel.attr('style', $toggleLinkRel.attr('style').replace('display: none;', ''));
      }
    });

    var $hiddenElements = $(Gustavus.Concert.hiddenElementSelector);

    $hiddenElements.each(function() {
      // show all of our hidden elements so they don't get deleteted by tinymce if removing the element after it.
      // Also helps editing. But reduces "preview" effect.
      $(this).show();
    });

    // remove fancy ampersand html
    $('abbr[title=and]', currObj).each(function() {
      this.parentNode.innerHTML = this.parentNode.innerHTML.replace(this.outerHTML, '&amp;');
    });
  },

  /**
   * Displays togglable links
   * @param  {HTMLElement} currObj object toggle links in
   * @return {undefined}
   */
  toggleLinksDisplayed: function(currObj) {
    var $toggleLinks = $('a.toggleLink', currObj);
    $toggleLinks.each(function() {
      $toggleLink = $(this);
      $toggleLink.addClass('toggledOpen');
      var $toggleLinkRel = $($toggleLink.attr('rel'));
      $toggleLinkRel.show();
    });
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
      var $lastChild = $('div.editable.default > div:last-child');
      if ($lastChild.length) {
        $lastChild.after('<p class="concertInsertion">&nbsp;</p>');
      }
      // mark titles and sub titles so they get a different tinyMCE configuration
      $('#page-titles div.editable').removeClass('default').addClass('title');
      // same with local navigation
      $('#local-navigation div.editable').removeClass('default').addClass('siteNav');

      if ($(Gustavus.Concert.hiddenElementSelector).length) {
        // we have hidden elements that have been displayed
        // throw a message into concert messages
        Gustavus.Template.addUserMessage('Some hidden elements on this page have been displayed to help with editing.', 'message', 50);
      }

      Extend.add('page', function(thisObj) {
        // Wait until the template does it's thing, then destroy certain pieces.
        // remove additional HTML calling Extend.apply may have added.
        Gustavus.Concert.destroyTemplatePluginsPostApply(thisObj);
        Gustavus.Concert.toggleLinksDisplayed(thisObj);
        Gustavus.Concert.reInitTemplatePlugins(thisObj);
      }, 100);

      Gustavus.Concert.initilizeEditablePartsForEdits();
      Gustavus.Concert.initilizeEditablePartsForEdits = null;
    });
  }
};

$(document)
  .on('click', '#concertPublish', function(e) {
    e.preventDefault();
    var $this = $(this);
    if ($this.attr('disabled') !== 'disabled') {
      $this.attr('disabled', 'disabled');
      Gustavus.Concert.saveEdits('publish', true, true, $this);
    }
  })

  .on('click', '#concertSavePrivateDraft', function(e) {
    e.preventDefault();
    Gustavus.Concert.hasSharedDraft()
      .done(function(data) {
        if (data) {
          $('#confirmPrivateDraft').dialog({
            modal: true,
            buttons: {
              'Save private draft': function() {
                Gustavus.Concert.saveEdits('savePrivateDraft', false);
                $(this).dialog('close');
              },
              Cancel: function() {
                $(this).dialog('close');
              }
            }
          });
        } else {
          Gustavus.Concert.saveEdits('savePrivateDraft', true);
        }
      })
      .fail(function() {
        // something happened.
        alert('The draft was not successfully saved');
      })
  })

  .on('click', '#concertSavePublicDraft', function(e) {
    e.preventDefault();
    Gustavus.Concert.saveEdits('savePublicDraft', true);
  })

  .on('click', '#concertDiscardDraft', function(e) {
    e.preventDefault();
    Gustavus.Concert.hasSharedDraft()
      .done(function(data) {
        if (data) {
          $('#confirmDiscardDraft').dialog({
            modal: true,
            buttons: {
              'Discard Draft': function() {
                Gustavus.Concert.saveEdits('discardDraft', true);
                $(this).dialog('close');
              },
              Cancel: function() {
                $(this).dialog('close');
              }
            }
          });
        } else {
          Gustavus.Concert.saveEdits('discardDraft', true);
        }
      })
      .fail(function() {
        // something happened.
        alert('The draft was not successfully discarded');
      });
  })

  .on('click', '#concertStopEditing', function(e) {
    window.location = Gustavus.Utility.URL.urlify(Gustavus.Concert.redirectPath, {'concert': 'stopEditing', 'concertAction': 'menu'});
  })

  .on('click', 'a.quitConcert', function(e) {
    e.preventDefault();
    Gustavus.Concert.ignoreDirtyEditors = true;

    if (Gustavus.Concert.hasDirtyEditor()) {
      $('#confirmQuit').dialog({
        modal: true,
        buttons: {
          'Quit': function() {
            Gustavus.Concert.releaseLock();
            $(this).dialog('close');
            // redirect to the link's href
            window.location = e.target.href;
          },
          Cancel: function() {
            Gustavus.Concert.ignoreDirtyEditors = false;
            $(this).dialog('close');
          }
        }
      });
    } else {
      Gustavus.Concert.releaseLock();
      // redirect to the link's href
      window.location = e.target.href;
    }
  });

// When the user leaves the page release the lock.
$(window)
  .on('beforeunload', function() {
    if (!Gustavus.Concert.ignoreDirtyEditors && Gustavus.Concert.hasDirtyEditor()) {
      return 'It looks like you have made changes. You will lose these changes if you continue.';
    }
    $(window).off('unload');
    Gustavus.Concert.releaseLock(false);
  })
  .on('unload', function() {
    Gustavus.Concert.releaseLock(false);
  });