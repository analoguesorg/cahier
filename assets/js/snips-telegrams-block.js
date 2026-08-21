( function( blocks, element, blockEditor, components ) {
    var el = element.createElement;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;

    blocks.registerBlockType( 'snips/active-telegram', {
        title: 'Snip Active Telegram',
        icon: 'format-status',
        category: 'widgets',
        attributes: {
            badgeLabel: { type: 'string', default: '' },
            buttonText: { type: 'string', default: '' },
            footerNote: { type: 'string', default: '' }
        },
        edit: function( props ) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            var displayBadge  = attributes.badgeLabel || 'CURRENT INQUIRY';
            var displayButton = attributes.buttonText || 'Leave a Field Note ↓';
            var displayFooter = attributes.footerNote || 'Zero-login required • Open discussion';

            return el(
                element.Fragment,
                null,
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: 'Ledger Settings', initialOpen: true },
                        el( TextControl, {
                            label: 'Kicker Badge Label',
                            value: attributes.badgeLabel,
                            placeholder: 'Default: CURRENT INQUIRY',
                            onChange: function( val ) { setAttributes( { badgeLabel: val } ); }
                        } ),
                        el( TextControl, {
                            label: 'Action Button Label',
                            value: attributes.buttonText,
                            placeholder: 'Default: Leave a Field Note ↓',
                            onChange: function( val ) { setAttributes( { buttonText: val } ); }
                        } ),
                        el( TextControl, {
                            label: 'Composer Footnote',
                            value: attributes.footerNote,
                            placeholder: 'Default: Zero-login required • Open discussion',
                            onChange: function( val ) { setAttributes( { footerNote: val } ); }
                        } )
                    )
                ),
                el(
                    'div',
                    { 
                        style: { 
                            background: '#0c0d10', 
                            border: '1px solid #1f1f23', 
                            borderRadius: '6px', 
                            color: '#ffffff',
                            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                            overflow: 'hidden'
                        } 
                    },
                    el( 'div', { style: { padding: '24px 28px 18px 28px' } },
                        el( 'div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px', fontFamily: 'ui-monospace, monospace', fontSize: '0.72rem' } },
                            el( 'div', { style: { display: 'flex', gap: '10px', alignItems: 'center' } },
                                el( 'span', { style: { background: '#ffffff', color: '#000000', padding: '2px 6px', fontWeight: '800', borderRadius: '3px' } }, displayBadge ),
                                el( 'span', { style: { color: '#71717a' } }, 'TELEGRAM' ),
                                el( 'span', { style: { color: '#52525b' } }, 'AUG 2026' )
                            ),
                            el( 'div', { style: { background: 'rgba(16, 185, 129, 0.15)', color: '#10b981', border: '1px solid #059669', padding: '2px 7px', borderRadius: '3px', fontSize: '0.68rem' } }, '● 5D 14H REMAINING' )
                        ),
                        el( 'h2', { style: { color: '#ffffff', fontSize: '1.25rem', margin: '0 0 8px 0', fontWeight: '700' } }, 'Contiguous Field Ledger Prompt' ),
                        el( 'p', { style: { color: '#a1a1aa', fontSize: '0.88rem', margin: '0 0 16px 0', lineHeight: '1.5' } }, 'Published prompt will render at the top, streaming dispatches seamlessly below.' ),
                        el( 'div', { style: { display: 'flex', gap: '14px', alignItems: 'center' } },
                            el( 'span', { style: { border: '1px solid #3f3f46', padding: '6px 12px', borderRadius: '3px', fontSize: '0.8rem', color: '#fff', background: '#1e2024' } }, displayButton ),
                            el( 'span', { style: { color: '#71717a', fontSize: '0.75rem', fontFamily: 'ui-monospace, monospace' } }, displayFooter )
                        )
                    ),
                    el( 'div', { style: { padding: '16px 28px', color: '#71717a', fontSize: '0.82rem', fontStyle: 'italic', borderTop: '1px solid #18191c' } }, 'Dispatches stream live here.' ),
                    el( 'div', { style: { padding: '16px 28px', background: '#09090b', borderTop: '1px solid #18191c' } },
                        el( 'div', { style: { background: '#121316', border: '1px solid #27272a', borderRadius: '4px', padding: '8px 12px', color: '#71717a', fontSize: '0.82rem', fontFamily: 'ui-monospace, monospace' } }, '> Record a field note...' )
                    )
                )
            );
        },
        save: function() {
            return null;
        }
    } );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components );