/* eslint-disable no-var */
import type * as blocks from '@wordpress/blocks';
import type * as blockEditor from '@wordpress/block-editor';
import type * as element from '@wordpress/element';
import type * as data from '@wordpress/data';

declare global {
	var wp: {
		blocks: typeof blocks;
		blockEditor: typeof blockEditor & {
			BlockContextProvider: React.ComponentType<{
				children: React.ReactNode;
				value: Record<string, unknown>;
			}>;
		};
		element: typeof element;
		serverSideRender: React.ComponentType<{
			block: string;
			attributes: Record<string, unknown>;
		}>;
		data: typeof data;
	};
}
