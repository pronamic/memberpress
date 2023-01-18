/**
 * WordPress components that create the necessary UI elements for the block
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-components/
 */
import { InspectorControls, RichText, MediaUpload, MediaUploadCheck } from "@wordpress/block-editor";
import {
  PanelBody, ToggleControl, ColorPicker, SelectControl
} from "@wordpress/components";
import { Flex } from '@wordpress/components';
import { __experimentalText as Text } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-block-editor/#useBlockProps
 */
import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import { useSelect } from '@wordpress/data';

const ALLOWED_MEDIA_TYPES = ['image'];

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#edit
 *
 * @param {Object}   props               Properties passed to the function.
 * @param {Object}   props.attributes    Available block attributes.
 * @param {Function} props.setAttributes Function that updates individual attributes.
 *
 * @return {WPElement} Element to render.
 */
export default function Edit({ attributes, setAttributes }) {
  const blockProps = useBlockProps();
  const { group_id, show_title, button_highlight_color } = attributes;

  const { options } = useSelect((select) => {
    let groups = window.memberpressBlocks.groups;
    let defaultOption = [{label: __("Select Group", "memberpress"), value: ''}];
    return {
      options: defaultOption.concat(groups),
    };
  });

  return (
    <div {...blockProps}>

      <InspectorControls>
        <PanelBody title="Options" initialOpen={true}>

          <SelectControl
            label={__("Group", "memberpress")}
            value={group_id}
            options={options}
            onChange={(val) => {
              setAttributes({ group_id: val })
            }}
            __nextHasNoMarginBottom
          />

          <ToggleControl
            label={__("Show Global Pricing Headline", "memberpress")}
            checked={show_title}
            onChange={() => setAttributes({ show_title: !show_title })}
          />


          <Flex direction="column">
            <Text>Button Highlight Color</Text>
            <ColorPicker
              label="Show Title"
              color={button_highlight_color}
              onChange={(val) => setAttributes({ button_highlight_color: val })}
              defaultValue="#EF1010"
            />
          </Flex>

        </PanelBody>
      </InspectorControls>

      <ServerSideRender
        block="memberpress/pro-pricing-table"
        attributes={{ show_title, button_highlight_color, group_id }}
      />
    </div>
  );
}
