const { registerBlockType } = wp.blocks;
const { __ } = wp.i18n;

/**
 * Internal dependencies
 */
import Edit from './edit';

registerBlockType("memberpress/account-links", {
    title: __("Account Links", "memberpress"),
    icon: "editor-ul",
    category: "memberpress",
    description: __("Display the list of MemberPress Account links.", "memberpress"),
    keywords: [__("membership account links", "memberpress")],
    attributes: {},
    supports: {
        customClassName: false, // Removes "Custom CSS Class" from "Advanced" tab of block
        html: false // User cannot edit block as HTML
    },
    edit: Edit,
    save: function() {
        return null; // Null because we're rendering the output serverside
    }
});
