import { Disabled } from "@wordpress/components";

import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit() {
  const blockProps = useBlockProps();

  return (
    <div {...blockProps}>
      <Disabled>
        <ServerSideRender
          block="memberpress/account-links"
        />
      </Disabled>
    </div>
  );
}
