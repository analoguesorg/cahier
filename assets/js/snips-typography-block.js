/**
 * Snips Typography - Gutenberg Block Inspector Extension & Glyph Palette
 */
 
(function (wp) {
  if (!wp || !wp.hooks || !wp.element || !wp.blockEditor || !wp.components) {
    return;
  }

  var el = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var useState = wp.element.useState;
  var addFilter = wp.hooks.addFilter;

  var InspectorControls = wp.blockEditor.InspectorControls;
  var PanelBody = wp.components.PanelBody;
  var PanelRow = wp.components.PanelRow;
  var ToggleControl = wp.components.ToggleControl;
  var Button = wp.components.Button;
  var Modal = wp.components.Modal;
  var SelectControl = wp.components.SelectControl;
  var TextControl = wp.components.TextControl;

  var TARGET_BLOCKS = [
    'core/paragraph',
    'core/heading',
    'core/quote',
    'core/list',
    'core/list-item',
    'core/code',
    'core/site-title',
  ];

  var OPENTYPE_CONFIG = [
    { tag: 'calt', label: 'Contextual Alternates (calt)' },
    { tag: 'liga', label: 'Standard Ligatures (liga)' },
    { tag: 'dlig', label: 'Discretionary Ligatures (dlig)' },
    { tag: 'tnum', label: 'Tabular Figures (tnum)' },
    { tag: 'zero', label: 'Slashed Zero (zero)' },
    { tag: 'onum', label: 'Oldstyle Figures (onum)' },
    { tag: 'frac', label: 'Fractions (frac)' },
    { tag: 'smcp', label: 'Small Capitals (smcp)' },
    { tag: 'case', label: 'Case-Sensitive Forms (case)' },
    { tag: 'ss01', label: 'Stylistic Set 01 (ss01)' },
    { tag: 'ss02', label: 'Stylistic Set 02 (ss02)' },
    { tag: 'ss03', label: 'Stylistic Set 03 (ss03)' },
    { tag: 'ss04', label: 'Stylistic Set 04 (ss04)' },
  ];

  var GLYPH_SETS = {
    punctuation: ['—', '–', '…', '“', '”', '‘', '’', '«', '»', '‹', '›', '•', '·', '§', '¶', '†', '‡', '№', '©', '®', '™', '°', '′', '″'],
    arrows: ['←', '→', '↑', '↓', '↔', '↕', '↖', '↗', '↘', '↙', '⇐', '⇒', '⇔', '➔', '▲', '▼', '◀', '▶', '△', '▽', '◁', '▷'],
    math: ['±', '×', '÷', '≠', '≈', '≤', '≥', '≡', '¬', '√', '∞', '∫', '∑', '∏', '∆', '∇', '∈', '∉', '⊂', '⊃', '∪', '∩', '∂'],
    currency: ['$', '€', '£', '¥', '₹', '₽', '₩', '₿', '¢', '¤', 'ƒ', '฿', '₫', '₴', '₱', '₲', '₸', '₺'],
    geometry: ['■', '□', '▲', '△', '◆', '◇', '○', '●', '◎', '◈', '◊', '★', '☆', '✕', '✓', '✔', '✗', '✘', '⌘', '⌥', '⇧', '⌫'],
    fractions: ['½', '⅓', '⅔', '¼', '¾', '⅕', '⅖', '⅗', '⅘', '⅙', '⅚', '⅛', '⅜', '⅝', '⅞', '⁄', '‰', '‱'],
  };

  function addTypographyAttributes(settings, name) {
    if (TARGET_BLOCKS.indexOf(name) === -1) {
      return settings;
    }
    settings.attributes = Object.assign({}, settings.attributes, {
      snipsOpenType: {
        type: 'object',
        default: {},
      },
    });
    return settings;
  }
  addFilter('blocks.registerBlockType', 'snips/typography-attributes', addTypographyAttributes);

  var withAdvancedTypography = wp.compose.createHigherOrderComponent(function (BlockEdit) {
    return function (props) {
      if (TARGET_BLOCKS.indexOf(props.name) === -1) {
        return el(BlockEdit, props);
      }

      var attributes = props.attributes;
      var setAttributes = props.setAttributes;
      var openType = attributes.snipsOpenType || {};

      var modalState = useState(false);
      var isModalOpen = modalState[0];
      var setIsModalOpen = modalState[1];

      var catState = useState('punctuation');
      var selectedCat = catState[0];
      var setSelectedCat = catState[1];

      var searchState = useState('');
      var glyphSearch = searchState[0];
      var setGlyphSearch = searchState[1];

      function toggleFeature(tag, value) {
        var updated = Object.assign({}, openType);
        if (value) {
          updated[tag] = 1;
        } else {
          delete updated[tag];
        }
        setAttributes({ snipsOpenType: updated });
      }

      var featureString = Object.keys(openType)
        .map(function (t) {
          return '"' + t + '" 1';
        })
        .join(', ');

      var customStyle = featureString ? { fontFeatureSettings: featureString } : {};

      function insertGlyph(glyph) {
        var content = attributes.content || '';
        setAttributes({ content: content + glyph });
      }

      var activeGlyphs = GLYPH_SETS[selectedCat] || [];
      if (glyphSearch) {
        activeGlyphs = activeGlyphs.filter(function (g) {
          return g.indexOf(glyphSearch) !== -1;
        });
      }

      return el(
        Fragment,
        null,
        el(BlockEdit, Object.assign({}, props, { style: customStyle })),
        el(
          InspectorControls,
          null,
          el(
            PanelBody,
            {
              title: 'Advanced Glyphs & Typographic Features',
              initialOpen: false,
              icon: 'editor-textcolor',
            },
            el(
              PanelRow,
              null,
              el(
                Button,
                {
                  isSecondary: true,
                  isSmall: true,
                  style: { width: '100%', marginBottom: '12px' },
                  onClick: function () {
                    setIsModalOpen(true);
                  },
                },
                'Open Character & Glyph Palette'
              )
            ),
            OPENTYPE_CONFIG.map(function (feat) {
              return el(ToggleControl, {
                key: feat.tag,
                label: feat.label,
                checked: !!openType[feat.tag],
                onChange: function (val) {
                  toggleFeature(feat.tag, val);
                },
              });
            })
          )
        ),
        isModalOpen &&
          el(
            Modal,
            {
              title: 'Character & Glyph Palette',
              onRequestClose: function () {
                setIsModalOpen(false);
              },
              style: { maxWidth: '580px', width: '90vw' },
            },
            el(
              'div',
              { style: { display: 'flex', gap: '12px', marginBottom: '14px' } },
              el(SelectControl, {
                value: selectedCat,
                options: [
                  { label: 'Punctuation & Symbols', value: 'punctuation' },
                  { label: 'Directional Arrows', value: 'arrows' },
                  { label: 'Mathematical Operators', value: 'math' },
                  { label: 'Currency', value: 'currency' },
                  { label: 'Geometric Forms & UI', value: 'geometry' },
                  { label: 'Fractions & Ratios', value: 'fractions' },
                ],
                onChange: function (val) {
                  setSelectedCat(val);
                },
                style: { flex: 1 },
              }),
              el(TextControl, {
                placeholder: 'Filter glyphs...',
                value: glyphSearch,
                onChange: function (val) {
                  setGlyphSearch(val);
                },
                style: { width: '150px' },
              })
            ),
            el(
              'div',
              {
                style: {
                  display: 'grid',
                  gridTemplateColumns: 'repeat(auto-fill, minmax(44px, 1fr))',
                  gap: '6px',
                  maxHeight: '320px',
                  overflowY: 'auto',
                  background: '#0c0d10',
                  padding: '12px',
                  borderRadius: '4px',
                  border: '1px solid #1f1f23',
                },
              },
              activeGlyphs.map(function (g, idx) {
                return el(
                  'button',
                  {
                    key: idx,
                    type: 'button',
                    title: 'Insert ' + g,
                    onClick: function () {
                      insertGlyph(g);
                    },
                    style: {
                      background: '#18191c',
                      color: '#f4f4f5',
                      border: '1px solid #27272a',
                      borderRadius: '3px',
                      height: '42px',
                      fontSize: '18px',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      cursor: 'pointer',
                    },
                  },
                  g
                );
              })
            )
          )
      );
    };
  }, 'withAdvancedTypography');

  addFilter('editor.BlockEdit', 'snips/advanced-typography-control', withAdvancedTypography);

  function applyOpenTypeFrontend(extraProps, blockType, attributes) {
    if (TARGET_BLOCKS.indexOf(blockType.name) === -1) {
      return extraProps;
    }
    var openType = attributes.snipsOpenType || {};
    var tags = Object.keys(openType);
    if (!tags.length) {
      return extraProps;
    }

    var featureString = tags
      .map(function (t) {
        return '"' + t + '" 1';
      })
      .join(', ');

    var existingStyle = extraProps.style || {};
    extraProps.style = Object.assign({}, existingStyle, {
      fontFeatureSettings: featureString,
    });

    return extraProps;
  }
  addFilter('blocks.getSaveContent.extraProps', 'snips/apply-opentype-frontend', applyOpenTypeFrontend);
})(window.wp);