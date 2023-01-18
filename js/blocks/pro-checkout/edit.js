/**
 * WordPress components that create the necessary UI elements for the block
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-components/
 */
import { InspectorControls } from "@wordpress/block-editor";
import {
  PanelBody, ToggleControl, ColorPicker, SelectControl
} from "@wordpress/components";
import { __experimentalText as Text, Disabled, Flex } from '@wordpress/components';

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
const { __ } = wp.i18n;

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
  const blockProps = useBlockProps({
    className: 'wp-block',
  });

  const { membership_id, show_title, button_highlight_color } = attributes;

  const { options } = useSelect((select) => {
    let memberships = window.memberpressBlocks.memberships;
    let defaultOption = [{label: __("Select Membership", "memberpress"), value: ''}];
    return {
      options: defaultOption.concat(memberships),
    };
  });

  return (
    <div {...blockProps}>

      <InspectorControls>
        <PanelBody title="Options" initialOpen={true}>

          <SelectControl
            label="Membership"
            value={membership_id}
            options={options}
            onChange={(val) => {
              setAttributes({ membership_id: val })
            }}
            __nextHasNoMarginBottom
          />

        </PanelBody>
      </InspectorControls>

      <Disabled>
        <ServerSideRender
          block="memberpress/checkout"
          attributes={{ membership_id }}
        />
      </Disabled>

    </div>
  );
}
