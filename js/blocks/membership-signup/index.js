const { registerBlockType } = wp.blocks;
const { __ } = wp.i18n;

/**
 * Internal dependencies
 */
import Edit from './edit';
import './editor.scss';

registerBlockType("memberpress/membership-signup", {
  title: __("Registration", "memberpress"),
  icon: "groups", // https://developer.wordpress.org/resource/dashicons/
  category: "memberpress",
  description: __(
    "Display a signup form for a MemberPress membership.",
    "memberpress"
  ),
  keywords: [__("membership signup form", "memberpress")],
  attributes: {
    membership: { type: "string" }
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
