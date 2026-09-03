(function (wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls } = wp.blockEditor || wp.editor;
    const { PanelBody, SelectControl, TextControl, RangeControl, ColorPalette } = wp.components;
    const serverSideRender = wp.serverSideRender;

    const config = window.cahierAviationDefaults || { settings: {}, presets: {} };
    const defaults = config.settings;

    registerBlockType('cahier/aviation', {
        title: 'Cahier Aviation',
        icon: 'airplane',
        category: 'widgets',
        description: 'METAR station weather, ticker tape, and flight category badges.',
        attributes: {
            displayType: {
                type: 'string',
                default: 'ticker',
            },
            icao: {
                type: 'string',
                default: defaults.default_stations || 'KBOS, KORH, KMVY',
            },
            preset: {
                type: 'string',
                default: defaults.preset || 'terminal_green',
            },
            bgColor: {
                type: 'string',
                default: '',
            },
            textColor: {
                type: 'string',
                default: '',
            },
            speed: {
                type: 'number',
                default: 0,
            },
        },
        edit: function (props) {
            const { attributes, setAttributes } = props;

            const presetOptions = [
                { label: 'Use Global Settings', value: 'default' },
                { label: 'Terminal Green (Classic)', value: 'terminal_green' },
                { label: 'Dark Monolith', value: 'dark_monolith' },
                { label: 'Amber CRT', value: 'amber_crt' },
                { label: 'Editorial Parchment', value: 'parchment' },
            ];

            return el(Fragment, {}, [
                el(InspectorControls, { key: 'inspector' }, [
                    el(PanelBody, { title: 'Display Configuration', initialOpen: true }, [
                        el(SelectControl, {
                            label: 'Display Format',
                            value: attributes.displayType,
                            options: [
                                { label: 'Animated Ticker Tape', value: 'ticker' },
                                { label: 'Raw METAR Transmission', value: 'raw' },
                                { label: 'Flight Category Badge', value: 'category' },
                            ],
                            onChange: function (val) {
                                setAttributes({ displayType: val });
                            },
                        }),
                        el(TextControl, {
                            label: 'Airport Stations',
                            value: attributes.icao,
                            help: '3 or 4 letter codes separated by spaces or commas (e.g. bos orh kmvy).',
                            onChange: function (val) {
                                setAttributes({ icao: val });
                            },
                        }),
                    ]),
                    attributes.displayType === 'ticker' && el(PanelBody, { title: 'Style Overrides', initialOpen: false }, [
                        el(SelectControl, {
                            label: 'Design Preset',
                            value: attributes.preset,
                            options: presetOptions,
                            onChange: function (val) {
                                if (val !== 'default' && config.presets[val]) {
                                    const p = config.presets[val];
                                    setAttributes({
                                        preset: val,
                                        bgColor: p.bg_color,
                                        textColor: p.text_color,
                                        speed: p.speed,
                                    });
                                } else {
                                    setAttributes({ preset: val, bgColor: '', textColor: '', speed: 0 });
                                }
                            },
                        }),
                        el('p', { style: { fontSize: '11px', color: '#666', marginBottom: '8px' } }, 'Background Color Override:'),
                        el(ColorPalette, {
                            value: attributes.bgColor,
                            onChange: function (val) {
                                setAttributes({ bgColor: val || '' });
                            },
                        }),
                        el('p', { style: { fontSize: '11px', color: '#666', marginBottom: '8px' } }, 'Text Color Override:'),
                        el(ColorPalette, {
                            value: attributes.textColor,
                            onChange: function (val) {
                                setAttributes({ textColor: val || '' });
                            },
                        }),
                        el(RangeControl, {
                            label: 'Scroll Speed (Characters Per Second)',
                            value: attributes.speed || defaults.speed || 12,
                            min: 4,
                            max: 30,
                            help: 'Calibrates velocity by character count so ticker speed never changes when stations are added or removed.',
                            onChange: function (val) {
                                setAttributes({ speed: val });
                            },
                        }),
                    ]),
                ]),
                el('div', { className: 'cahier-aviation-block-canvas', style: { minHeight: '40px' } }, [
                    el(serverSideRender, {
                        block: 'cahier/aviation',
                        attributes: attributes,
                    }),
                ]),
            ]);
        },
        save: function () {
            return null;
        },
    });
})(window.wp);