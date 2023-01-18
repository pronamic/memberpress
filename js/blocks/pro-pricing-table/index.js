/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/#registering-a-block
 */
 import { registerBlockType } from '@wordpress/blocks';
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
 registerBlockType( 'memberpress/pro-pricing-table', {
  title: __("MP ReadyLaunch™ Pricing Table", "memberpress"),
  icon: "money-alt", // https://developer.wordpress.org/resource/dashicons/
  category: "memberpress",
  description: __("MemberPress Pricing Table Options", "memberpress"),
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
 } );
