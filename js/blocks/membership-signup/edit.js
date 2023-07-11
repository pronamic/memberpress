import { __ } from '@wordpress/i18n';
import { InspectorControls } from "@wordpress/block-editor";
import { PanelBody, Disabled, SelectControl } from "@wordpress/components";

import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import { useSelect } from '@wordpress/data';

export default function Edit({ attributes, setAttributes }) {
  const blockProps = useBlockProps();
  const { membership } = attributes;

  const { options } = useSelect((select) => {
    let memberships = window.memberpressBlocks.memberships;
    let defaultOption = [{label: __("-- Select a Membership", "memberpress"), value: ''}];
    return {
      options: defaultOption.concat(memberships),
    };
  });

  return (
    <div {...blockProps}>
      <InspectorControls>
        <PanelBody title="MemberPress Signup Form" initialOpen={true}>
          <SelectControl
            label={ __("Select a Membership registration form to display", "memberpress") }
            multiple={ false }
            value={membership}
            options={options}
            onChange={(val) => {
              setAttributes({ membership: val })
            }}
          />
        </PanelBody>
      </InspectorControls>

      <Disabled>
        <ServerSideRender
          block="memberpress/membership-signup"
          attributes={{ membership }}
        />
      </Disabled>
    </div>
  );
}
