( function( blocks, element, blockEditor, components ) {
    var el = element.createElement;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var RangeControl = components.RangeControl;
    var ToggleControl = components.ToggleControl;

    blocks.registerBlockType( 'snips/discord-frame', {
        title: 'Snip Discord Panel',
        icon: 'format-chat',
        category: 'widgets',
        attributes: {
            serverId: { type: 'string', default: '' },
            channelId: { type: 'string', default: '' },
            height: { type: 'number', default: 580 },
            matchFull: { type: 'boolean', default: true }
        },
        edit: function( props ) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            var renderHeight = attributes.matchFull ? 580 : ( attributes.height || 540 );

            return el(
                element.Fragment,
                null,
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: 'Panel Sizing & Embed Settings', initialOpen: true },
                        el( ToggleControl, {
                            label: 'Match Full Column Height (100%)',
                            checked: attributes.matchFull,
                            onChange: function( val ) { setAttributes( { matchFull: val } ); }
                        } ),
                        ! attributes.matchFull && el( RangeControl, {
                            label: 'Fixed Height (px)',
                            value: attributes.height,
                            min: 300,
                            max: 1200,
                            step: 10,
                            onChange: function( val ) { setAttributes( { height: val } ); }
                        } ),
                        el( TextControl, {
                            label: 'Server ID',
                            value: attributes.serverId,
                            placeholder: 'Enter Server ID',
                            onChange: function( val ) { setAttributes( { serverId: val } ); }
                        } ),
                        el( TextControl, {
                            label: 'Channel ID (Optional)',
                            value: attributes.channelId,
                            placeholder: 'e.g. 123456789012345678',
                            onChange: function( val ) { setAttributes( { channelId: val } ); }
                        } )
                    )
                ),
                el(
                    'div',
                    { 
                        style: { 
                            height: renderHeight + 'px', 
                            background: '#313338', 
                            border: '1px solid #1f1f23', 
                            borderRadius: '4px', 
                            display: 'flex', 
                            flexDirection: 'column', 
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                            overflow: 'hidden'
                        } 
                    },
                    el(
                        'div',
                        { style: { background: '#2b2d31', padding: '10px 14px', borderBottom: '1px solid #1f1f23', display: 'flex', alignItems: 'center', justifyContent: 'space-between' } },
                        el( 'div', { style: { display: 'flex', alignItems: 'center', gap: '8px' } },
                            el( 'span', { style: { color: '#80848e', fontSize: '18px', fontWeight: 'bold' } }, '#' ),
                            el( 'span', { style: { color: '#f2f3f5', fontSize: '13px', fontWeight: '600' } }, 'general' )
                        ),
                        el( 'span', { style: { color: '#949ba4', fontSize: '11px', fontFamily: 'ui-monospace, monospace' } }, attributes.matchFull ? 'Auto Full-Height (100%)' : attributes.height + 'px' )
                    ),
                    el(
                        'div',
                        { style: { flex: '1', padding: '16px', display: 'flex', flexDirection: 'column', gap: '14px', opacity: 0.6 } },
                        el(
                            'div',
                            { style: { display: 'flex', gap: '10px', alignItems: 'flex-start' } },
                            el( 'div', { style: { width: '32px', height: '32px', borderRadius: '50%', background: '#5865f2' } } ),
                            el( 'div', null,
                                el( 'div', { style: { display: 'flex', gap: '6px', alignItems: 'center' } },
                                    el( 'span', { style: { color: '#f2f3f5', fontSize: '12px', fontWeight: '600' } }, 'WidgetBot' ),
                                    el( 'span', { style: { color: '#949ba4', fontSize: '10px' } }, 'Today at 4:12 PM' )
                                ),
                                el( 'div', { style: { color: '#dbdee1', fontSize: '12px', marginTop: '2px' } }, 'Live chat feed active. Ready for incoming dispatches.' )
                            )
                        ),
                        el(
                            'div',
                            { style: { display: 'flex', gap: '10px', alignItems: 'flex-start' } },
                            el( 'div', { style: { width: '32px', height: '32px', borderRadius: '50%', background: '#23a55a' } } ),
                            el( 'div', null,
                                el( 'div', { style: { display: 'flex', gap: '6px', alignItems: 'center' } },
                                    el( 'span', { style: { color: '#f2f3f5', fontSize: '12px', fontWeight: '600' } }, 'Guest' ),
                                    el( 'span', { style: { color: '#949ba4', fontSize: '10px' } }, 'Today at 4:14 PM' )
                                ),
                                el( 'div', { style: { color: '#dbdee1', fontSize: '12px', marginTop: '2px' } }, 'Guest access enabled with zero login required.' )
                            )
                        )
                    ),
                    el(
                        'div',
                        { style: { padding: '10px 14px', background: '#2b2d31' } },
                        el(
                            'div',
                            { style: { background: '#383a40', padding: '8px 12px', borderRadius: '4px', color: '#80848e', fontSize: '12px' } },
                            'Message #general...'
                        )
                    )
                )
            );
        },
        save: function() {
            return null;
        }
    } );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components );