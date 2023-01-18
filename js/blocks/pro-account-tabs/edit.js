/**
 * WordPress components that create the necessary UI elements for the block
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-components/
 */
import { InspectorControls, RichText, MediaUpload, MediaUploadCheck } from "@wordpress/block-editor";
import {
  PanelBody, ToggleControl, Disabled, Button, ResponsiveWrapper, Spinner
} from "@wordpress/components";

import { __experimentalText as Text } from '@wordpress/components';

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
    className: 'alignwide wp-block',
  });

  const { show_welcome_image, welcome_image } = attributes;

  const onUpdateImage = (image) => {
    setAttributes({
      welcome_image: image.url,
    });
  };

  const onRemoveImage = () => {
    setAttributes({
      welcome_image: '',
    });
  };

  const instructions = <p>{__('To edit the Welcome image, you need permission to upload media.', 'image-selector-example')}</p>;

  return (
    <div {...blockProps}>

      <InspectorControls>
        <PanelBody title="Options" initialOpen={true}>

          <ToggleControl
            label="Show Welcome Image"
            checked={show_welcome_image}
            onChange={() => setAttributes({ show_welcome_image: !show_welcome_image })}
          />
          {show_welcome_image && <div className="editor-post-featured-image">
            <MediaUploadCheck fallback={instructions}>
              <MediaUpload
                title={__('Welcome image', 'image-selector-example')}
                onSelect={onUpdateImage}
                allowedTypes={ALLOWED_MEDIA_TYPES}
                value={welcome_image}
                render={({ open }) => (
                  <div className="editor-post-featured-image__container">
                  <Button
                    className={!welcome_image ? 'editor-post-featured-image__toggle' : 'editor-post-featured-image__preview'}
                    onClick={open}>
                    {!welcome_image && (__('Set Welcome image', 'image-selector-example'))}
                    {!!welcome_image &&
                      <ResponsiveWrapper
                      naturalWidth={2000}
                      naturalHeight={2000}
                      isInline
                      >
                        <img className="mepr-editor-media-preview-img" src={welcome_image} alt={__('Welcome image', 'image-selector-example')} />
                      </ResponsiveWrapper>
                    }
                  </Button>
                  </div>
                )}
              />
            </MediaUploadCheck>

            {!!welcome_image &&
              <MediaUploadCheck>
                <MediaUpload
                  title={__('Welcome image', 'image-selector-example')}
                  onSelect={onUpdateImage}
                  allowedTypes={ALLOWED_MEDIA_TYPES}
                  value={welcome_image}
                  render={({ open }) => (
                    <Button onClick={open} isDefault isLarge>
                      {__('Replace Welcome Image', 'image-selector-example')}
                    </Button>
                  )}
                />
              </MediaUploadCheck>
            }
            {!!welcome_image &&
              <MediaUploadCheck>
                <Button onClick={onRemoveImage} className="" isLink isDestructive>
                  {__('Remove Welcome Image', 'image-selector-example')}
                </Button>
              </MediaUploadCheck>
            }
          </div>}
        </PanelBody>
      </InspectorControls>

      <Disabled>
        <ServerSideRender
          block="memberpress/pro-account-tabs"
          attributes={{ welcome_image, show_welcome_image }}
        />
      </Disabled>

    </div>
  );
}
