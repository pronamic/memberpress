const { registerBlockType } = wp.blocks;
const { __ } = wp.i18n;

/**
 * Internal dependencies
 */
import Edit from './edit';
import './editor.scss';

registerBlockType("memberpress/login-form", {
  title: __("Login Form", "memberpress"),
  icon: "admin-network", // https://developer.wordpress.org/resource/dashicons/
  category: "memberpress",
  description: __("Display the MemberPress Login form", "memberpress"),
  keywords: [__("membership login form", "memberpress")],
  attributes: {
    use_redirect: { type: "boolean" }
  },
  supports: {
    customClassName: false, // Removes "Custom CSS Class" from "Advanced" tab of block
    html: false // User cannot edit block as HTML
  },
  edit: Edit,
  save: function() {
    return null; // Null because we're rendering the output serverside
  }
});
