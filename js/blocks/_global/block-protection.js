import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Fragment } from '@wordpress/element';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import BrandIcon from '../../../brand/components/BrandIcon';

const withProtectionControls = createHigherOrderComponent((BlockEdit) => {
    return (props) => {
        const { attributes, setAttributes, name } = props;

        // Skip protection controls for blocks in the exclusion list.
        if (memberpressBlocks.block_protection_exclude && memberpressBlocks.block_protection_exclude.includes(name)) {
            return <BlockEdit {...props} />;
        }

        return (
            <Fragment>
                <BlockEdit {...props} />
                <InspectorControls>
                    <PanelBody
                        title={
                            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                <BrandIcon size={20} />
                                {__('Content Protection', 'memberpress')}
                            </div>
                        }
                        initialOpen={false}
                    >
                        <SelectControl
                            label={__('Access Rule', 'memberpress')}
                            value={attributes.mepr_protection_rule || 0}
                            options={[
                                { label: __('None', 'memberpress'), value: 0 },
                                ...memberpressBlocks.rules
                            ]}
                            onChange={(value) => setAttributes({ mepr_protection_rule: parseInt(value) })}
                        />
                        <SelectControl
                            label={__('If Allowed', 'memberpress')}
                            value={attributes.mepr_protection_ifallowed || 'show'}
                            options={[
                                { label: __('Show', 'memberpress'), value: 'show' },
                                { label: __('Hide', 'memberpress'), value: 'hide' }
                            ]}
                            onChange={(value) => setAttributes({ mepr_protection_ifallowed: value })}
                        />
                        <SelectControl
                            label={__('Unauthorized Access', 'memberpress')}
                            value={attributes.mepr_protection_unauth || 'hide'}
                            options={[
                                { label: __('Hide', 'memberpress'), value: 'hide' },
                                { label: __('Default', 'memberpress'), value: 'default' },
                                { label: __('Display Message', 'memberpress'), value: 'message' }
                            ]}
                            onChange={(value) => setAttributes({ mepr_protection_unauth: value })}
                        />
                        {attributes.mepr_protection_unauth === 'message' && (
                            <TextControl
                                label={__('Unauthorized Message', 'memberpress')}
                                value={attributes.mepr_protection_unauth_message || ''}
                                onChange={(value) => setAttributes({ mepr_protection_unauth_message: value })}
                            />
                        )}
                    </PanelBody>
                </InspectorControls>
            </Fragment>
        );
    };
}, 'withProtectionControls');

addFilter('editor.BlockEdit', 'memberpress/with-protection-controls', withProtectionControls);
