import { __ } from '@wordpress/i18n';
import { InspectorControls } from "@wordpress/block-editor";
import { PanelBody, Disabled, SelectControl } from "@wordpress/components";

import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import { useSelect } from '@wordpress/data';

export default function Edit({ attributes, setAttributes }) {
  const blockProps = useBlockProps();
  const { field } = attributes;
  const { fields } = useSelect((select) => {
    let customFields = window.memberpressBlocks.custom_fields;
    let defaultOptions = [
      {label: 'full_name', value: 'full_name'},
      {label: 'full_name_last_first', value: 'full_name_last_first'},
      {label: 'full_name_last_initial', value: 'full_name_last_initial'},
      {label: 'last_name_first_initial', value: 'last_name_first_initial'},
      {label: 'first_name', value: 'first_name'},
      {label: 'last_name', value: 'last_name'},
      {label: 'user_login', value: 'user_login'},
      {label: 'user_email', value: 'user_email'},
      {label: 'nickname', value: 'nickname'},
      {label: 'description', value: 'description'},
      {label: 'mepr-address-one', value: 'mepr-address-one'},
      {label: 'mepr-address-two', value: 'mepr-address-two'},
      {label: 'mepr-address-city', value: 'mepr-address-city'},
      {label: 'mepr-address-state', value: 'mepr-address-state'},
      {label: 'mepr-address-zip', value: 'mepr-address-zip'},
      {label: 'mepr-address-country', value: 'mepr-address-country'},
      {label: 'mepr_user_message', value: 'mepr_user_message'},
      {label: 'user_registered', value: 'user_registered'},
      {label: 'display_name', value: 'display_name'},
      {label: 'ID', value: 'ID'},
    ];
    return {
      fields: defaultOptions.concat(customFields),
    };
  });

  return (
    <div {...blockProps}>
      <InspectorControls>
        <PanelBody title="MemberPress Account Info" initialOpen={true}>
          <SelectControl
            label={ __("Field Slug:", "memberpress") }
            value={ field }
            multiple={ false }
            options={ fields }
            onChange={(val) => setAttributes({ field: val })}
          />
        </PanelBody>
      </InspectorControls>

      <Disabled>
        <ServerSideRender
          block="memberpress/account-info"
          attributes={{ field }}
        />
      </Disabled>
    </div>
  );
}
