const { registerBlockType } = wp.blocks;
const { __ } = wp.i18n;

/**
 * Internal dependencies
 */
import Edit from './edit';
import './editor.scss';

registerBlockType("memberpress/subscriptions", {
  title: __("Subscriptions", "memberpress"),
  icon: "groups",
  category: "memberpress",
  description: __("Display the subscriptions list of currently logged-in user.", "memberpress"),
  keywords: [__("membership subscriptions", "memberpress")],
  supports: {
    customClassName: false, // Removes "Custom CSS Class" from "Advanced" tab of block
    html: false // User cannot edit block as HTML
  },
  edit: Edit,
  save: function() {
    return null; // Null because we're rendering the output serverside
  }
});
