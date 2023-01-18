/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/#registering-a-block
 */
import { registerBlockType } from '@wordpress/blocks';
import './editor.scss';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Edit from './edit';

/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/#registering-a-block
 */
registerBlockType('memberpress/pro-login-form', {
  title: __("MP ReadyLaunch™ Login", "memberpress"),
  icon: "admin-network", // https://developer.wordpress.org/resource/dashicons/
  category: "memberpress",
  description: __("MemberPress Checkout", "memberpress"),
  supports: {
    customClassName: false, // Removes "Custom CSS Class" from "Advanced" tab of block
    html: false // User cannot edit block as HTML
  },

  /**
   * @see ./edit.js
   */
  edit: Edit,

  /**
   * @see ./save.js
   */
  save: function () {
    return null; // Null because we're rendering the output serverside
  }
});
