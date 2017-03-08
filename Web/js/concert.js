// Make sure our Gustavus pseudo namespace exists
if(!window.Gustavus) {
  window.Gustavus = {};
}

Gustavus.ConcertConstants = {
  /**
   * Global attributes to be used for the valid_elements configuration of tinyMCE
   * @type {String}
   */
  globalAttributes: 'accesskey|class|contenteditable|contextmenu|data-+|dir|draggable|dropzone|hidden|id|lang|spellcheck|style|tabindex|title|translate',

  /**
   * Event attributes to be used for the valid_elements configuration of tinyMCE
   *   Note: Event attributes aren't currently supported by tinymce, but if we ever needed them, we would have to utilize this.
   *   @todo Use this if needed. It currently isn't used anywhere.
   * @type {String}
   */
  eventAttributes: "onabort|onblur|oncancel|oncanplay|oncanplaythrough|onchange|onclick|onclose|oncontextmenu|oncuechange|ondblclick|ondrag|ondragend|ondragenter|ondragleave|ondragover|ondragstart|ondrop|ondurationchange|onemptied|onended|onerror|onfocus|oninput|oninvalid|onkeydown|onkeypress|onkeyup|onload|onloadeddata|onloadedmetadata|onloadstart|onmousedown|onmousemove|onmouseout|onmouseover|onmouseup|onmousewheel|onpause|onplay|onplaying|onprogress|onratechange|onreset|onscroll|onseeked|onseeking|onseeking|onselect|onshow|onstalled|onsubmit|onsuspend|ontimeupdate|onvolumechange|onwaiting"
};

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
   * Storage for destroyed elements that we need to add back in after editing
   * @type {Object}
   */
  destroyedElements: {},

  /**
   * Selector of elements that get dynamically hidden via template javascript
   * @type {String}
   */
  hiddenElementSelector: 'div.editable .JSHide, div.editable .doShow, div.editable .doHide, div.editable .doToggle, div.editable .doFadeToggle, div.editable .nodisplay',

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
      {title: 'Lead In', selector: 'p', classes: 'leadin'},
      {title: 'Small', selector: '*', inline : 'span',  classes: 'small'},
      {title: 'Remove Sorting', selector: 'table', classes: 'nosort'},
      {title: 'Fancy', selector: 'img', classes: 'fancy'},
      {title: 'Left', selector: 'img,div.boxContainer', cmd: 'addLeftClass'},
      {title: 'Right', selector: 'img,div.boxContainer', cmd: 'addRightClass'},
      {title: 'Button', selector: 'a', classes: 'button'},
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
      {title: 'Button', selector: 'a', classes: 'button'},
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
    theme: 'modern',
    convert_urls: false, // prevent messing with URLs
    allow_script_urls: false,
    relative_urls: false,

    forced_root_block: '',
    element_format: 'html',
    schema: 'html5',
    //force_p_newlines: true, deprecated
    invalid_elements: 'script',
    // allow i, all attrs in spans (pullquotes), and remove empty li's and ul's
    // we don't want to allow * for attributes because some text editors use random attributes that get inserted when using copy\paste (Libre Office vs. Word).
    extended_valid_elements: 'i[class],' +
      'span[rel|' + Gustavus.ConcertConstants.globalAttributes + '],' +
      '-li[type|value|' + Gustavus.ConcertConstants.globalAttributes + '],' +
      '-ul[compact|type|' + Gustavus.ConcertConstants.globalAttributes + '],' +
      '+svg[class|data-icon-toggle|role|aria*],' +
      '+desc[id],' +
      '+use[xlink+]',
    // invalid styles. This can exclude styles for particular elements as well.
    invalid_styles: {
      '*': 'font-face font-family'
    },
    // ensure anchors can contain headings.
    valid_children: '+a[h2|h3|h4|h5|h6|div|p]',
    // we don't want it to convert rgb to hex.
    force_hex_style_colors: false,
    // we don't want to keep our styles when we hit return/enter.
    keep_styles: false,
    // disable indenting/outdenting things with padding. (Only allows nesting lists, and other elements.)
    indentation: false,
    // when hitting return inside of a container, it first duplicates the current child element, the next return will pull that child element outside of the parent.
    end_container_on_empty_block: true,

    //invalid_styles http://www.tinymce.com/wiki.php/Configuration:invalid_elements
    //keep_styles http://www.tinymce.com/wiki.php/Configuration:keep_styles
    menubar: 'edit insert view format table tools',
    toolbar: "insertfile undo redo | styleselect | bold italic | bullist numlist | link anchor image responsivefilemanager | spellchecker improvedcode",
    // right click menu
    contextmenu: "link image inserttable | cell row column deletetable | list spellchecker",
    // set our customized menu.
    menu : {
      file   : {title : 'File'  , items : 'newdocument'},
      edit   : {title : 'Edit'  , items : 'undo redo | searchreplace | cut copy paste pastetext | selectall'},
      insert : {title : 'Insert', items : 'link media | hr template'},
      view   : {title : 'View'  , items : 'visualaid visualblocks'},
      format : {title : 'Styles', items : 'bold italic underline strikethrough superscript subscript | list formats | clearformat'},
      table  : {title : 'Table' , items : 'inserttable tableprops deletetable | cell row column'},
      tools  : {title : 'Tools' , items : 'spellchecker'}
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
    image_title: true,
    image_description: true,
    filemanager_title: "Concert File Manager" ,
    external_plugins: {"filemanager" : "/concert/filemanager/plugin.min.js?v=1", "filebrowser": "/concert/filebrowser/filebrowser.min.js?v=1"},

    noneditable_noneditable_class: 'concertNonEditable',

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
        Gustavus.Concert.editorBlur(editor);
      });
      // add html into any empty i and svg elements so they don't get removed
      editor.on('beforeSetContent', function(e) {
        var $content = $('<div>' + e.content + '</div>');
        $('i', $content).each(function() {
          var $this = $(this);
          if ($this.html() === '') {
            $this.html('<span class="concertInsertion nodisplay">concert</span>');
          }
        });
        $('svg', $content).each(function() {
          var $this = $(this);
          if (!$this.find('.concertInsertion').length) {
            $this.append('<title class="concertInsertion nodisplay">concert</title>');
          }
        });
        e.content = $content.html();
      });

      editor.on('init', function(e) {
        // hijack the open function so we can do extra operations if needed
        editor.windowManager.origOpen = editor.windowManager.open;
        editor.windowManager.open = function(args, params) {
          // check to see if this is an image window.
          if (typeof args.data == 'object' && args.title === 'Insert/edit image') {
            // we are working with images
            // hijack the onSubmit function so we can do our checks
            args.origSubmit = args.onSubmit;
            args.onSubmit = function(e) {
              if (!e.data.alt) {
                // we don't see an alt property
                editor.windowManager.alert("Please specify an image description", function() {
                  if (editor.windowManager.windows.length > 0) {
                    // focus our last window
                    editor.windowManager.windows[editor.windowManager.windows.length - 1].focus();
                  }
                });
                return false;
              }
              // adjust our dimensions in case the image is too big
              var adjustedDims = Gustavus.Concert.resizeImagesIfNeeded(e.data.width, e.data.height);
              if (adjustedDims.width !== e.data.width) {
                if (e.target.find('#width')) {
                  // only set the width because constrain being true will automatically adjust the height
                  e.target.find('#width').value(adjustedDims.width.toString());
                }
                if (e.target.find('#height')) {
                  // remove our height so we don't distort the image if it is in a container less than 1200
                  e.target.find('#height').value(null);
                }
              }
              // call the original onSubmit function
              return args.origSubmit(e);
            };
            // check to see if gimli has a crop and un-check the constrain checkbox
            if (args.data.src && args.data.src.match(/\/gimli\/.*?c\d+:\d+/)) {
              // look for our constrain proportions checkbox in the body
              // this is gross, but should be more future proof that using the current indexes within the args
              for (var a in args.body) {
                if (args.body[a] && args.body[a].items) {
                  // our item has sub items
                  for (var b in args.body[a].items) {
                    if (args.body[a].items[b].name === 'constrain') {
                      // we found our constrain item
                      args.body[a].items[b].checked = false;
                      break;
                    }
                  }
                }
              }
            }
          }
          // call the original open function
          return editor.windowManager.origOpen(args, params);
        };

        // hijack insertAfter so we can remove classes if it appears to be copying the classes of the element we want to insert after
        editor.dom.origInsertAfter = editor.dom.insertAfter;
        editor.dom.insertAfter = function(node, referenceNode) {
          if (node.className === referenceNode.className) {
            // remove the class if it appears that the class name was copied
            node.removeAttribute('class');
          }
          return editor.dom.origInsertAfter(node, referenceNode);
        }

        // hijack replace, so we can make sure that we don't replace grid blocks with other elements.
        editor.dom.origReplace = editor.dom.replace;
        editor.dom.replace = function(newElm, oldElm, keepChildren) {
          if (oldElm && typeof oldElm === 'object' && oldElm.tagName && oldElm.tagName.match(/^(P|DIV)$/i) && oldElm.className && oldElm.className.match(/grid-/)) {
            newElm.className = '';
            // we have a paragraph or div with a grid class
            // pull our innerHTML from our old element into the new element.
            newElm.innerHTML = oldElm.innerHTML;
            // replace the inner html of the old element with the outer html of our new element that now contains the inner html of the old element.
            oldElm.innerHTML = newElm.outerHTML;

            // we want the new element to be an empty version of the old element.
            // we do this so things that the original editor.dom.replace does will still get processed.
            newElm = oldElm.cloneNode();
            newElm.innerHTML = '';
          }
          editor.dom.origReplace(newElm, oldElm, keepChildren);
        }

        // We need to hijack split so we can remove any classes applied to the element we are splitting. (This fixes not being able to get rid of grid classes on containers)
        editor.dom.origSplit = editor.dom.split;
        editor.dom.split = function(parentElm, splitElm, replacementElm) {
          if (replacementElm) {
            replacementElm.className = '';
          } else {
            splitElm.className = '';
          }
          editor.dom.origSplit(parentElm, splitElm, replacementElm);
        };
      });

      // Add a command for adding our left class to elements.
      // This needs to grab the parent container for iframes
      editor.addCommand('addLeftClass', function() {
        var selectedNode, box, boxContainer;
        selectedNode = editor.selection.getNode();
        if (selectedNode.getAttribute('data-mce-object') === 'iframe' && (box = selectedNode.parentNode) && (boxContainer = box.parentNode) && boxContainer.className.indexOf('boxContainer') !== -1) {
          // change the selectedNode to be the boxContainer so we can add our class to that element
          selectedNode = boxContainer;
        }

        if (selectedNode.className.indexOf('left') !== -1) {
          // we have a left class already. We want to remove it.
          selectedNode.className = selectedNode.className.replace('left', '');
        } else if (selectedNode.className.indexOf('right') !== -1) {
          // we have a right class. We want to swap right for left.
          selectedNode.className = selectedNode.className.replace('right', 'left');
        } else {
          // we don't have a left class or a right class. Add our left class
          selectedNode.className = selectedNode.className + ' left';
        }
      });
      // add a custom query state handler so we can see if an element has a class of left or not.
      editor.addQueryStateHandler('addLeftClass', function() {
        var selectedNode, box, boxContainer;
        selectedNode = editor.selection.getNode();
        if (selectedNode.getAttribute('data-mce-object') === 'iframe' && (box = selectedNode.parentNode) && (boxContainer = box.parentNode) && boxContainer.className.indexOf('boxContainer') !== -1) {
          selectedNode = boxContainer;
        }
        if (selectedNode.className.indexOf('left') !== -1) {
          return true;
        }
        return false;
      });
      // Add a command for adding our right class to elements.
      // This needs to grab the parent container for iframes
      editor.addCommand('addRightClass', function() {
        var selectedNode, box, boxContainer;
        selectedNode = editor.selection.getNode();
        if (selectedNode.getAttribute('data-mce-object') === 'iframe' && (box = selectedNode.parentNode) && (boxContainer = box.parentNode) && boxContainer.className.indexOf('boxContainer') !== -1) {
          // change the selectedNode to be the boxContainer so we can add our class to that element
          selectedNode = boxContainer;
        }

        if (selectedNode.className.indexOf('right') !== -1) {
          // we have a right class already. We want to remove it.
          selectedNode.className = selectedNode.className.replace('right', '');
        } else if (selectedNode.className.indexOf('left') !== -1) {
          // we have a left class. We want to swap left for right.
          selectedNode.className = selectedNode.className.replace('left', 'right');
        } else {
          // we don't have a right class or a left class. Add our right class
          selectedNode.className = selectedNode.className + ' right';
        }
      });
      // add a custom query state handler so we can see if an element has a class of right or not.
      editor.addQueryStateHandler('addRightClass', function() {
        var selectedNode, box, boxContainer;
        selectedNode = editor.selection.getNode();
        if (selectedNode.getAttribute('data-mce-object') === 'iframe' && (box = selectedNode.parentNode) && (boxContainer = box.parentNode) && boxContainer.className.indexOf('boxContainer') !== -1) {
          selectedNode = boxContainer;
        }
        if (selectedNode.className.indexOf('right') !== -1) {
          return true;
        }
        return false;
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
   * Callback for tinymce blur
   *
   * @param {Object}  editor  The editor instance we are setting blur up for
   */
  editorBlur: function(editor) {
    // destroy any plugins that we want to be re-enabled when apply gets called
    Gustavus.Concert.destroyTemplatePluginsPreApply(editor.getElement());
    // run any filters added to 'page' in case a filter adds styles to any elements. (fancy tables)
    Extend.apply('page', editor.getElement());

    Gustavus.Concert.ignoreDirtyEditors = false;
    // clean up content
    var content = Gustavus.Concert.mceCleanup(editor.getContent());

    // convert images into GIMLI URLs.
    content = Gustavus.Concert.convertImageURLsToGIMLI(content);
    // convert embedded videos into responsive boxes
    content = Gustavus.Concert.convertEmbedsIntoResponsiveBoxes(content);

    editor.setContent(content);
    // remove any image placeholders
    Gustavus.Concert.removeImagePlaceholders();
  },

  /**
   * Resets our blur action on the editors
   */
  reInitEditorBlur: function() {
    // reset our blur action that we removed
    for (var i in tinyMCE.editors) {
      tinyMCE.editors[i].on('blur', function(e) {
        Gustavus.Concert.editorBlur(tinyMCE.editors[i]);
      });
    };
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
      "spellchecker hr template noneditable" //http://www.tinymce.com/wiki.php/Plugin:spellchecker
    ];

    if (this.allowCode || this.isAdmin) {
      plugins.push('improvedcode');
    }

    config.plugins  = plugins;
    config.selector = "div.editable.default";

    config.style_formats = this.tinyMceDefaultMenu;
    config.forced_root_block = 'p';

    config.template_replace_values = {
      mceTmpl: function(element) {
        // we want to get rid of the mceTmpl div
        element.outerHTML = element.innerHTML;
      }
    };
    config.templates = '/concert/js/tinymce/templates/templates.json';
    config.template_popup_height = 250;

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
      "spellchecker noneditable"
    ];

    if (this.isAdmin) {
      plugins.push('improvedcode');
    }

    config.plugins  = plugins;
    config.selector = "div.editable.title";

    config.style_formats = this.tinyMceTitleMenu;

    config.toolbar = "undo redo | styleselect | bold italic | link | spellchecker improvedcode";

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
      "spellchecker noneditable"
    ];

    if (this.isAdmin) {
      plugins.push('improvedcode');
    }

    config.plugins  = plugins;
    config.selector = "div.editable.siteNav";

    config.style_formats = this.tinyMceSiteNavMenu;
    // add class noFancyAmpersands to h2 and h3 elements that get inserted
    config.formats = {
      h2: {block: 'h2', 'classes': 'noFancyAmpersands'},
      h3: {block: 'h3', 'classes': 'noFancyAmpersands'}
    };

    config.toolbar = "undo redo | styleselect | bold italic | bullist | link | spellchecker improvedcode";

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
   * @param  {Integer} editableIndex Index of the current editable content
   * @return {String} Cleaned content
   */
  cleanUpContent: function(content, isSiteNav, editableIndex) {
    var cleaned = Gustavus.Concert.mceCleanup(content);
    // re add anything we destroyed
    cleaned = Gustavus.Concert.reAddDestroyedElements(cleaned, editableIndex)
    // convert images to be responsive
    cleaned = Gustavus.Concert.makeImagesResponsive(cleaned);
    cleaned = cleaned.replace(/<br.data-mce[^>]*>/g, '');
    // get rid of mce-item* stuff that tinymce doesn't always clean up.
    cleaned = cleaned.replace(/ ?mce-item[^>" ]*/g, '');
    // get rid of any empty classes we may have.
    cleaned = cleaned.replace(/class=""/g, '');

    var $content = $('<div>' + cleaned + '</div>');
    // remove any concert-remove elements
    $content.find('[data-concert-remove]').remove();
    // remove image placeholders
    Gustavus.Concert.removeImagePlaceholders($content);

    // remove toggledOpen class and svg's from toggleable links
    $content.find('a.toggleLink').each(function() {
      var $toggleLink = $(this);
      $toggleLink.removeClass('toggledOpen');
      $toggleLink.find('svg').remove();
      // remove any non-breaking spaces that occur immediately after an ">" since the template adds a non-breaking space after the toggleIcon svg's
      $toggleLink.html($toggleLink.html().replace(/\>(?:\&nbsp\;)+/, '>'));
    });

    // re-add a use statement in case svgForEveryone modified it
    $content.find('svg').each(function() {
      var $svg = $(this);
      if ($svg.attr('data-icon-toggle')) {
        // swap toggleable icons back to their original state
        if ($svg.attr('data-icon-original')) {
          $svg.removeClass($svg.attr('data-icon-toggle').addClass($svg.attr('data-icon-original')));
          $svg.attr('data-icon-original', '');
        }
      }
      var svgClasses = $svg.attr('class').split(/\s+/);
      var icon = null;
      for (var i in svgClasses) {
        if (svgClasses[i].match(/icon-\w+/)) {
          icon = svgClasses[i];
          break;
        }
      }
      if (icon) {
        var title = $svg.find('title:not(.concertInsertion)');
        var desc  = $svg.find('desc:not(.concertInsertion)');
        var titleDesc = '';
        if (title.length && title[0].outerHTML) {
          titleDesc += title[0].outerHTML;
        }
        if (desc.length && desc[0].outerHTML) {
          titleDesc += desc[0].outerHTML;
        }

        if ($svg.find('use').attr('xlink:href')) {
          var xlinkHref = $svg.find('use').attr('xlink:href');
        } else {
          var iconPath = $svg.data('svg-file') ? $svg.data('svg-file') : '/template/css/icons/icons.svg';
          var xlinkHref = iconPath + '#' + icon;
        }
        $svg.html(titleDesc + '<use xlink:href="' + xlinkHref + '"></use>');
      }
    });

    // Clean up tables
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
      var $trimmed = $table.find('[data-footable-trimmed]');
      if ($trimmed) {
        // remove stuff added in when trimming tables
        $trimmed.removeAttr('data-hide data-footable-trimmed').css('display', '');
        $table.find('td[style="display: none;"]').css('display', '');
      }
      // remove anything else footable adds. (thead and possibly more)
      $table.find('[data-footable-added]').remove();
    });

    // remove any concertInsertions in i's and svg's that we added to make sure the i tag itself didn't get stripped
    $('i span.concertInsertion, svg .concertInsertion', $content).each(function() {
      $(this).remove();
    });

    if (isSiteNav) {
      // clean up site nav stuff.
      Gustavus.Template.compressLinkTitles($content);
    }
    cleaned = $content.html();

    return cleaned;
  },

  /**
   * Makes our images responsive by converting them to gimli and removing the width and height attributes. This has to be called when building the edit objects. Tinymce relies on the width and height attributes being set
   * @param  {String} content Content to convert images in
   * @return {String}
   */
  makeImagesResponsive: function(content) {
    // wrap everything in a div so we only have one jquery object to work with
    var $content = $('<div>' + content + '</div>');

    $content.find('img').each(function() {
      var $this = $(this);
      var width = $this.attr('width');
      var height = $this.attr('height');
      var src = $this.attr('src');

      var url = Gustavus.Utility.URL.parseURL(src);
      if (url.pathname && (!url.host || Gustavus.Utility.URL.isGustavusHost(url.host))) {
        // convertImageURLsToGIMLI should take care of all of these, but just in case.
        if (url.pathname.indexOf('/slir/') === 0) {
          // we have a slir request. We want this converted to gimli.
          url.pathname = url.pathname.replace('/slir/', '/gimli/');
          $this.attr('src', Gustavus.Utility.URL.buildURL(url));
        } else if (url.pathname.indexOf('/gimli/') !== 0 && (width || height)) {
          // we have a width and height set, but it isn't in gimli yet.
          // Convert it
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

        // remove width and height
        $this.attr('width', null);
        $this.attr('height', null);

      }
    });
    return $content.html();
  },

  /**
   * Adds our height and width attributes to images from gimli so we can maintain aspect ratios. This should be called right before tiny mce is initialized.
   * @return {undefined}
   */
  addImageWidthAndHeightAttributesFromGIMLI: function() {
    var $images = $('div.editable img');

    $images.each(function() {
      var $this = $(this);
      var width  = $this.attr('width');
      var height = $this.attr('height');
      var src = $this.attr('src');

      var url = Gustavus.Utility.URL.parseURL(src);
      if (url.pathname && (!url.host || Gustavus.Utility.URL.isGustavusHost(url.host))) {
        if (url.pathname.indexOf('/slir/') === 0) {
          // we have a slir request. We want this converted to gimli.
          url.pathname = url.pathname.replace('/slir/', '/gimli/');
        }
        if (url.pathname.indexOf('/gimli/') === 0) {
          // we have a gimli url
          // lets get the width and height out of it
          var widthMatches = url.pathname.match('^/gimli/[^w]*?(w[,.:x]?([0-9]+))');
          var gimliWidth = widthMatches ? widthMatches[2] : null;

          var heightMatches = url.pathname.match('^/gimli/[^h]*?(h[,.:x]?([0-9]+))');
          var gimliHeight = heightMatches ? heightMatches[2] : null;

          if (!width) {
            width  = gimliWidth;
          }
          if (!height) {
            height = gimliHeight;
          }
        }

        // set our width and height
        $this.attr('width', width);
        $this.attr('height', height);

        $this.attr('src', Gustavus.Utility.URL.buildURL(url));
      }
    });
  },

  /**
   * Resizes images to have a max width of 1200 if they are bigger than that.
   * @param  {integer} width  Image width
   * @param  {integer} height Image height
   * @return {object}  Object with properties of the new width and height
   */
  resizeImagesIfNeeded: function(width, height) {
    if (width && height && width > 1200) {
      var ratio = height / width;
      width = 1200
      height = width * ratio;
    } else if (width && width > 1200) {
      width = 1200;
    }
    return {'width': width, 'height': height};
  },

  /**
   * Adds an image placeholder so we know an image is loading in case GIMLI takes awhile to generate the image
   * @param  {jQuery} $img JQuery object representing the image to put the placeholder in for
   * @return {undefined}
   */
  addImagePlaceholder: function($img) {
    // throw our placeholder after the image
    $img.after('<span class="spinner" data-img-placeholder style="display:block;"></span>');
    var style = $img.attr('style');
    if (style && style.indexOf('height') >= 0) {
      // save our original height so we can restore later
      $img.attr('data-orig-height', $img.css('height'));
    }
    // set the height to be zero so we can see the placeholder and know that it is loading
    $img.css('height', '0px');
  },

  /**
   * Removes any image placeholders once images load
   * @return {undefined}
   */
  removeImagePlaceholders: function($content) {
    // function to actually remove image placeholders
    var removeFunc = function() {
      var $this = $(this);
      $this.next('.spinner[data-img-placeholder]').remove();
      // we need to adjust the height now since we set it to be zero when we threw the placeholder in.
      if ($this.data('orig-height')) {
        // reset our height to the original value
        $this.css('height', $this.data('orig-height'));
        $this.removeAttr('data-orig-height');
      } else {
        $this.css('height', '');
      }
      var mceStyle = $this.attr('data-mce-style');
      if (mceStyle) {
        // remove height from mce-style since it would have been set to 0
        mceStyle = mceStyle.replace(/height.+?;/, '');
        $this.attr('data-mce-style', mceStyle);
      }
    };
    if ($content) {
      $('img', $content).each(removeFunc);
    } else {
      $('img').load(removeFunc);
    }
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
      var src = $this.attr('src');

      var url = Gustavus.Utility.URL.parseURL(src);
      if (url.pathname && (!url.host || Gustavus.Utility.URL.isGustavusHost(url.host))) {
        // we are either a relative path, or a Gustavus host
        var width  = $this.attr('width');
        var height = $this.attr('height');

        resizedDims = Gustavus.Concert.resizeImagesIfNeeded(width, height);
        width  = resizedDims.width;
        height = resizedDims.height;

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
            // nothing to do.
            // return true to continue our .each loop
            return true;
          }
          // now we need to adjust the gimli parameters
          if (currentWidth) {
            if (!width) {
              // remove width from gimli
              url.pathname = url.pathname.replace(widthMatches[1], '');
            } else {
              url.pathname = url.pathname.replace(widthMatches[1], 'w' + width);
            }
          } else if (width) {
            // we don't have a width in gimli, but do in the width attribute. Add our new width after the height if it exists
            var heightMatch = url.pathname.match(/gimli\/(h\d+)/);
            if (heightMatch) {
              var replacement = (height ? heightMatch[1] : '');
              // we need to add our width after the height
              url.pathname = url.pathname.replace(heightMatch[1], replacement + '-w' + width);
            } else {
              url.pathname = url.pathname.replace('/gimli/', '/gimli/w' + width);
            }
          }
          if (currentHeight) {
            if (!height) {
              // remove height from gimli
              url.pathname = url.pathname.replace(heightMatches[1], '');
            } else {
              url.pathname = url.pathname.replace(heightMatches[1], 'h' + height);
            }
          } else if (height) {
            // we don't have a height in gimli, but do in the height attribute. Add our new height after the width if it exists
            var widthMatch = url.pathname.match(/gimli\/(w\d+)/);
            if (widthMatch) {
              var replacement = (width ? widthMatch[1] : '');
              // we need to add our height after the width
              url.pathname = url.pathname.replace(widthMatch[1], replacement + '-h' + height);
            } else {
              url.pathname = url.pathname.replace('/gimli/', '/gimli/h' + height);
            }
          }
          // clean up any rogue dashes
          url.pathname = url.pathname.replace(/gimli\/(w\d+|h\d+)-\//, 'gimli/$1/');
          Gustavus.Concert.addImagePlaceholder($this);
          $this.attr('src', Gustavus.Utility.URL.buildURL(url));
        } else {
          // we don't yet have a gimli url. We might want to build one.
          url.pathname = Gustavus.Utility.URL.toAbsolute(url.pathname);
          if (width || height) {
            // we have a width or height set. We want to convert to gimli
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
          }
          Gustavus.Concert.addImagePlaceholder($this);
          $this.attr('src', Gustavus.Utility.URL.buildURL(url));
        }
      }
    });

    return $content.html();
  },

  /**
   * Converts iframes into responsive boxes
   *
   * @param  {String} content Content to convert
   * @return {String}
   */
  convertEmbedsIntoResponsiveBoxes: function(content) {
    // wrap everything in a div so we only have one jquery object to work with
    var $content = $('<div>' + content + '</div>');
    $content.find('iframe').each(function() {
      var $this = $(this);
      var width = $this.attr('width').replace('px', '');
      var $parent = $this.parent();
      if ($parent.hasClass('box16x9') || $parent.hasClass('box4x3') || $parent.hasClass('boxWidescreen') || $parent.hasClass('boxFullscreen') || $parent.hasClass('responsiveBox')) {
        // this looks like it might already be wrapped. We just need to verify.
        if ($parent.parent().children().length === 1) {
          // this is the only element in the parent.
          // We need to adjust the width of the containing element
          if (width) {
            $parent.parent().css('max-width', width + 'px');
            $parent.parent().addClass('boxContainer');
          } else {
            $parent.parent().css('max-width', '');
            $parent.parent().addClass('boxContainer');
          }
        } else if (width) {
          // we need to wrap our parent element in a div with max-width
          var parent = $this.parent().get(0);
          parent.outerHTML = '<div class="boxContainer" style="max-width: ' + width + 'px;">' + parent.outerHTML + '</div>';
        }
      } else {
        // this iframe needs to be wrapped to become responsive
        if ($this.attr('src') && $this.attr('src').match(/youtube|vimeo/)) {
          var prefix = '<div class="box16x9">';
        } else {
          var prefix = '<div class="responsiveBox">';
        }
        var suffix = '</div>';
        if (width) {
          // we need to wrap this in a div with max-width set
          prefix = '<div class="boxContainer" style="max-width: ' + width + 'px;">' + prefix;
          suffix += '</div>';
        }
        this.outerHTML = prefix + this.outerHTML + suffix;
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
      // remove display style from hidden elements that may have been toggled
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
    tinymce.editors.forEach(function(editor) {
      if (editor.isDirty()) {
        var $element = $(editor.getElement());
        var isSiteNav = (editor.settings.selector === 'div.editable.siteNav');
        edits[$element.data('index')] = Gustavus.Concert.cleanUpContent(editor.getContent(), isSiteNav, $element.data('index'));
      }
    });
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

    for (var i in tinyMCE.editors) {
      // force blur
      tinyMCE.editors[i].fire('blur');
      // remove our blur trigger so we don't get a prompt to ask if we want to leave for touch screens
      tinyMCE.editors[i].off('blur');
    };

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
          Gustavus.Concert.reInitEditorBlur();
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
          Gustavus.Concert.reInitEditorBlur();
          if ($element) {
            $element.attr('disabled', '');
          }
        }
      },
      error: function() {
        $('body').removeClass('loading');
        Gustavus.Concert.ignoreDirtyEditors = false;
        Gustavus.Concert.reInitEditorBlur();
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
    };

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
    var dirtyEditor = false;
    for (var i in tinymce.editors) {
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
   * @param {Object} args additional arguments extend.apply passes
   * @return {undefined}
   */
  reInitTemplatePlugins: function(currObj, args) {
    if (currObj && args && args.editable) {
      var $autoCaptions = $('img.autocaption', currObj);
    } else {
      var $autoCaptions = $('div.editable img.autocaption');
    }
    // trigger load for jcaption
    $autoCaptions.trigger('load');
  },

  /**
   * Destroys any plugins the template adds before Extend.apply gets called
   * @param  {HTMLElement} currObj object we need destroy plugins in
   * @return {undefined}
   */
  destroyTemplatePluginsPreApply: function(currObj) {
    if (currObj) {
      var $autoCaptions = $('span.caption', currObj);
    } else {
      var $autoCaptions = $('div.editable span.caption');
    }
    // remove any jcaption stuff
    $autoCaptions.each(function() {
      var $this = $(this);
      var $img = $this.children('img.autocaption').first();
      if ($img) {
        var $titleElement = $this.find('span');
        var title = $titleElement ? $titleElement.html() : '';
        $img.attr('title', title);
        $img.data('loaded', '');

        $this.replaceWith($img);
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
   * @param {Object} args additional arguments extend.apply passes
   * @return {undefined}
   */
  destroyTemplatePluginsPostApply: function(currObj, args) {
    // remove fancy ampersand html
    $('abbr[title=and]', currObj).each(function() {
      this.outerHTML = '&amp;';
    });

    // destroy colorbox
    if (currObj && args && args.editable) {
      var $colorBoxElements = $('.thickbox', currObj);
    } else {
      var $colorBoxElements = $('div.editable .thickbox');
    }
    $colorBoxElements.each(function() {
      var $this = $(this);
      // make sure colorbox has been initialized on this element
      if ($this.data('colorbox')) {
        $this.colorbox.remove();
      }
    });
  },

  /**
   * Re-adds any elements that we destroyed before creating our editor instances
   * @param  {string} content       Content to add elements back onto
   * @param  {integer} editableIndex Editable index that content belongs to
   * @return {string}
   */
  reAddDestroyedElements: function(content, editableIndex) {
    // convert content to a jquery object
    var $content = $('<div>' + content + '</div>');
    $content.find('[data-concert-replacement-index]').each(function() {
      var $this = $(this);
      var index = $(this).attr('data-concert-replacement-index');
      if (Gustavus.Concert.destroyedElements[editableIndex][index]) {
        this.outerHTML = Gustavus.Concert.destroyedElements[editableIndex][index];
      }
    });
    return $content.html();
  },

  /**
   * Grab the items that bxSlider is attached to so we can store them for replacing the bxSlider instances on save.
   *   This prevents us from saving the html that bxSlider adds to elements
   * @return {undefined}
   */
  saveInitialBxSliderElements: function() {
    var $bx = $('.bx-wrapper');
    if ($bx.length > 0) {
      var $bxCopy = $($bx[0].outerHTML);
      $bxCopy.addClass('concertNonEditable');
      $bx.hide();
      $bx.after($bxCopy);
      $bx.detach();
      // grab our item we added bxSlider to
      // $bx.children().first() will be the bx-viewport. Then the viewport will contain the itm we added bxSlider to
      var $bxElement = $bx.children().first().children().first();
      // destroy bxSlider!
      $bxElement.data('bxSlider').destroySlider();
      // remove stuff left over from bxSlider
      $bxElement.find('[aria-hidden]').each(function() {
        $(this).removeAttr('aria-hidden');
      });
      // get our editable index
      var index = $bxCopy.closest('div.editable').data('index');
      if (!this.destroyedElements[index]) {
        this.destroyedElements[index] = [];
      }
      // save our initial item so we can re-add it later
      this.destroyedElements[index].push($bxElement[0].outerHTML);
      // add an attribute to the item we want to replace upon save
      $bxCopy.attr('data-concert-replacement-index', this.destroyedElements[index].length - 1);
    }
  },

  /**
   * Converts html4 elements to html5.
   *   This includes the name attribute to ids for anchors.
   *   More to come?
   * @return {undefined}
   */
  convertToHTML5: function() {
    $('div.editable a[name]').each(function() {
      var $this = $(this);
      if ($this.attr('href') && $this.attr('name')) {
        if ($this.attr('id') && $this.attr('id') === $this.attr('name')) {
          // id is the same as the name. remove the name
          $this.removeAttr('name');
          return;
        }
        // this anchor has an href and a name. We need to remove the name and insert an anchor before it
        $this.before('<a id="' + $this.attr('name') + '"></a>');
        $this.removeAttr('name');
      } else if (!$this.attr('href')) {
        // we don't have a link
        if ($this.attr('id') && $this.attr('name')) {
          // we just need to remove the name attribute, and we will be valid
          $this.removeAttr('name');
        } else if ($this.attr('name')) {
          // we just need to convert the name to an id and we will be valid.
          $this.attr('id', $this.attr('name'));
          $this.removeAttr('name');
        }
      }
    });
  },

  /**
   * Checks to see if the browser is supported
   *   Adds a message to the page and returns false if it is not supported
   *
   * @return {boolean} True if the browser is supported
   */
  checkBrowser: function() {
    var newerIE = /\bTrident\/[567]\b|\bMSIE (?:9|10)\.0\b/, webkit = /\bAppleWebKit\/(\d+)\b/, olderEdge = /\bEdge\/12\.(\d+)\b/;

    if (newerIE.test(navigator.userAgent) || (navigator.userAgent.match(olderEdge) || [])[1] < 10547 || (navigator.userAgent.match(webkit) || [])[1] < 537) {
      // we are using an old browser that doesn't like SVG's we want to avoid them editing pages
      $('#globalNotice').append('<div class="global-notification-message noticeMessage"><div class="grid-container"><div class="message-contents"><svg class="icon-info-circle" role="img" aria-labelledby="browserIconTitle browserIconDesc"><title id="browserIconTitle">Global Notice Icon</title><desc id="browserIconDesc">Small icon for a global notice</desc><use xlink:href="/template/css/icons/icons.svg#icon-info-circle"></use></svg> It looks like your browser is not supported by Concert. Please upgrade to a <a href="http://google.com/chrome">newer browser</a>.</div></div></div>');
      return false;
    }
    return true;
  },

  /**
   * Initializes concert
   * @return {undefined}
   */
  init: function() {
    $(function() {
      if (!Gustavus.Concert.checkBrowser()) {
        // our browser isn't supported
        return false;
      }
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
      $('#local-navigation div.editable a span.description, #local-navigation div.editable a span.nodisplay').remove();

      Extend.add('page', function(args) {
        // Wait until the template does it's thing, then destroy certain pieces.
        // remove additional HTML calling Extend.apply may have added.
        Gustavus.Concert.destroyTemplatePluginsPostApply(this, args);
        Gustavus.Concert.reInitTemplatePlugins(this, args);
      }, 100);
      // destroy template plugins so things don't get duplicated
      Gustavus.Concert.destroyTemplatePluginsPreApply($('div.editable'));
      // apply the page filter to make sure our stuff runs.
      Extend.apply('page', $('div.editable'), {'editable': true});

      Gustavus.Concert.addImageWidthAndHeightAttributesFromGIMLI();
      // save bxSlider elements since we lose bxSlider data once tinyMCE takes over so we can replace them with their initial content
      Gustavus.Concert.saveInitialBxSliderElements();
      // convert all html4 elements into their corresponding html5 element
      Gustavus.Concert.convertToHTML5();

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
      });
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
            // set a cookie so we know the user wants to quit.
            $.cookie('quitConcert', '1');
            // redirect to the link's href
            window.location = e.target.href;
          },
          Cancel: function() {
            Gustavus.Concert.ignoreDirtyEditors = false;
            $(this).dialog('close');
            $.cookie('quitConcert', '0');
          }
        }
      });
    } else {
      Gustavus.Concert.releaseLock();
      // set a cookie so we know the user wants to quit.
      $.cookie('quitConcert', '1');
      // redirect to the link's href
      window.location = e.target.href;
    }
  })
  .on('shown.Gustavus.jQuery.Dropdown', 'li.dropdown', function() {
    if ($(this).find('ul.menu-dropdown').is(':visible')) {
      Gustavus.Template.toggleIcon($(this).find('svg'));
    }
  })
  .on('hidden.Gustavus.jQuery.Dropdown', 'li.dropdown', function() {
    if (!$(this).find('ul.menu-dropdown').is(':visible')) {
      Gustavus.Template.toggleIcon($(this).find('svg'));
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