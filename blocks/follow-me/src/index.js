import { Placeholder, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { InnerBlocks, RichText } from "@wordpress/block-editor";

function getHTML() {
	return (
		<div>
			<input type="url" placeholder="https://example.com/" />
			<button disabled="disabled">{ __( 'Follow this site', 'friends' ) }</button>
		</div>
	);
}

registerBlockType( 'friends/follow-me', {
	apiVersion: 2,
	edit: function( { attributes, isSelected, setAttributes } ) {
		const content = __( 'Do you have your own blog? Enter your URL to connect with me!' );
		return (
			<div { ...useBlockProps() }>
				<form method="post">
					<InnerBlocks
						template={
							[
							['core/paragraph', { content }]
							]
						}
					/>

				{ getHTML() }
				</form>
			</div>
			);
	},
	save: () => {
        const blockProps = useBlockProps.save();

        return (
            <div { ...blockProps }>
                <InnerBlocks.Content />
                { getHTML() }
           </div>
        );
    },
} );
