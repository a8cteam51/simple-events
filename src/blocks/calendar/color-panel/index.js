/**
 * Dependencies
 */
import { PanelColorSettings } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Renders a color panel component with customizable attributes.
 *
 * @param {Object}   props               - The properties passed to the component.
 * @param {string}   props.title         - The title of the color panel.
 * @param {string}   props.bgAttr        - The attribute for the background color.
 * @param {string}   props.colorAttr     - The attribute for the text color.
 * @param {string}   props.borderAttr    - The attribute for the border color.
 * @param {Object}   props.attributes    - The attributes object.
 * @param {Function} props.setAttributes - The function to update the attributes.
 */
const ColorPanel = ( {
	title,
	bgAttr,
	colorAttr,
	borderAttr,
	attributes,
	setAttributes,
} ) => {
	return (
		<PanelBody title={ title } initialOpen={ false }>
			<PanelColorSettings
				colorSettings={ [
					{
						label: __( 'Background Color', 'simple-events' ),
						value: attributes[ bgAttr ],
						onChange: ( value ) =>
							setAttributes( { [ bgAttr ]: value } ),
						clearable: true,
					},
					{
						label: __( 'Text Color', 'simple-events' ),
						value: attributes[ colorAttr ],
						onChange: ( value ) =>
							setAttributes( { [ colorAttr ]: value } ),
						clearable: true,
					},
					{
						label: __( 'Border Color', 'simple-events' ),
						value: attributes[ borderAttr ],
						onChange: ( value ) =>
							setAttributes( { [ borderAttr ]: value } ),
						clearable: true,
					},
				] }
			/>
		</PanelBody>
	);
};

export default ColorPanel;
