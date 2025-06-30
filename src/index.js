import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import Edit from './edit';
import './editor.scss';
import './style.scss';

registerBlockType('stylized-anchor-link/post-link', {
    title: __('Stylized Anchor Link', 'stylized-anchor-link'),
    description: __('Insert a stylized link to another post.', 'stylized-anchor-link'),
    category: 'widgets',
    icon: 'admin-links',
    keywords: [
        __('link', 'stylized-anchor-link'),
        __('post', 'stylized-anchor-link'),
        __('anchor', 'stylized-anchor-link'),
    ],
    attributes: {
        postId: {
            type: 'number',
            default: 0
        },
        postTitle: {
            type: 'string',
            default: ''
        },
        postUrl: {
            type: 'string',
            default: ''
        }
    },
    edit: Edit,
    save: () => null, // server side rendering to ensure links are always up to date.
});