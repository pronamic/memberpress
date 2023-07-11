const { registerBlockType } = wp.blocks;
const { __ } = wp.i18n;

/**
 * Internal dependencies
 */
import Edit from './edit';

registerBlockType("memberpress/account-info", {
  title: __("Account Info", "memberpress"),
  icon: "admin-users",
  category: "memberpress",
  description: __("Display the user meta field, which is chosen by slug.", "memberpress"),
  keywords: [__("membership user info", "memberpress")],
  supports: {
    customClassName: false, // Removes "Custom CSS Class" from "Advanced" tab of block
    html: false // User cannot edit block as HTML
  },
  edit: Edit,
  save: function() {
    return null; // Null because we're rendering the output serverside
  }
});
