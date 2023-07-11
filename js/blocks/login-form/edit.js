import { __ } from '@wordpress/i18n';
import { InspectorControls } from "@wordpress/block-editor";
import { PanelBody, Disabled, ToggleControl } from "@wordpress/components";

import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit({ attributes, setAttributes }) {
  const blockProps = useBlockProps();
  const { use_redirect } = attributes;

  return (
    <div {...blockProps}>
      <InspectorControls>
        <PanelBody title="MemberPress Login Form" initialOpen={true}>

          <ToggleControl
            label={ __("Login Redirect URL?", "memberpress") }
            checked={ use_redirect }
            onChange={() => setAttributes({ use_redirect: !use_redirect })}
          />

        </PanelBody>
      </InspectorControls>

      <Disabled>
        <ServerSideRender
          block="memberpress/login-form"
          attributes={{ use_redirect }}
        />
      </Disabled>
    </div>
  );
}
