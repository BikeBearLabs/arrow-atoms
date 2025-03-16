/* eslint-disable no-var */
export {};

/** The frontend types, injected by api_frontend */
declare global {
	var AA: {
		/** The root URL of the REST API */
		root: string;
		/** The nonce for the REST API */
		nonce: string;
	};
}
