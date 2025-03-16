/* eslint-disable no-var */
/** @import {BlockConfiguration, BlockEditProps, BlockSaveProps} from '@wordpress/blocks' */
/** @import {MutableRefObject, ReactNode} from 'react' */
/** @typedef {typeof ArrowAtomBlock} ArrowAtomBlock */
{
	const {
		createElement: x,
		Fragment,
		useState,
		useEffect,
		useLayoutEffect,
		useRef,
		useCallback,
	} = React;
	const { registerBlockType } = wp.blocks;
	const { RawHTML } = wp.element;
	const { select } = wp.data;
	const { useInnerBlocksProps } = wp.blockEditor;

	/** @template T */
	class State {
		/** @typedef {(v: T) => (() => void) | void} Subscriber */
		/** @typedef {() => void} Invalidator */
		/** @typedef {(prev: T) => T} Updater */

		/** @private */
		subscribers = /** @type {Set<Subscriber>} */ (new Set());

		/** @private */
		invalidators = /** @type {Map<Subscriber, Invalidator>} */ (new Map());

		/** @type {T} */
		#value;

		get value() {
			return this.#value;
		}

		set value(/** @type {T} */ v) {
			if (v === this.#value) return;

			this.#value = v;
			for (const invalidator of this.invalidators.values()) invalidator();
			for (const subscriber of this.subscribers) {
				const ret = subscriber(this.#value);
				if (typeof ret === 'function')
					this.invalidators.set(subscriber, ret);
			}
		}

		constructor(/** @type {T} */ v) {
			this.#value = v;
		}

		subscribe(/** @type {Subscriber} */ fn) {
			this.subscribers.add(fn);

			return () => {
				this.subscribers.delete(fn);
				this.invalidators.delete(fn);
			};
		}

		use() {
			const [, setKey] = useState(0);
			useEffect(
				() =>
					this.subscribe(() => {
						setKey((v) => v + 1);
					}),
				[],
			);

			return /** @type {const} */ ([
				this.value,
				useCallback((/** @type {T | Updater} */ v) => {
					if (typeof v === 'function')
						this.value = /** @type {Updater} */ (v)(this.value);
					else this.value = v;
				}, []),
			]);
		}
	}

	class Islands {
		/**
		 * @private
		 * @type {Readonly<
		 * 	| { phase: 'idle' }
		 * 	| { phase: 'pending'; promise: Promise<string[]> }
		 * 	| { phase: 'resolved'; result: string[] }
		 * >}
		 */
		state = {
			phase: 'idle',
		};

		async refresh() {
			const { root, nonce } = AA;
			const postId = /** @type {string} */ (
				select('core/editor')['getCurrentPostId']()
			);
			const respPromise = fetch(
				`${root}/aa/v1/render/islands/post/${postId}`,
				{
					headers: {
						'x-wp-nonce': nonce,
					},
				},
			);
			const promise = respPromise.then(async (resp) => {
				if (!resp.ok) throw new Error('Failed to fetch islands');
				return resp.json();
			});
			this.state = { phase: 'pending', promise };
			try {
				const json = await promise;
				this.state = { phase: 'resolved', result: json };
				return json;
			} catch {
				this.state = { phase: 'idle' };
			}
		}

		async get() {
			switch (this.state.phase) {
				case 'idle':
					return (await this.refresh()) ?? [];
				case 'resolved':
					return this.state.result;
				case 'pending':
					return this.state.promise;
			}
		}

		use() {
			const [islands, setIslands] = useState(
				/** @type {string[] | null} */ (null),
			);
			useEffect(() => {
				void this.get().then(setIslands);
			}, []);
			return islands;
		}
	}

	if (typeof globalThis.ArrowAtomBlock === 'undefined') {
		/**
		 * @template {{ content: string }} T
		 * @implements {Partial<BlockConfiguration<T>>}
		 */
		var ArrowAtomBlock = class ArrowAtomBlock {
			/** @private */
			static islands = new Islands();

			/** @private */
			static ssrContainers = new State(/** @type {Node[]} */ ([]));

			static register(/** @type {string} */ name) {
				// @ts-expect-error the wp types are incorrect, this works
				registerBlockType(name, new this(name));
			}

			/** @type {string} */
			name;

			constructor(/** @type {string} */ name) {
				this.name = name;
				this.edit = this.edit.bind(this);
				this.save = this.save.bind(this);
			}

			edit(/** @type {BlockEditProps<T>} */ { attributes }) {
				const { content } = attributes;
				const innerBlocksProps =
					/** @type {{ children: ReactNode }} */ (
						useInnerBlocksProps()
					);
				const containerRef = useRef(
					/** @type {HTMLDivElement | null} */ (null),
				);
				const ssrContainerRef = useRef(
					/** @type {HTMLDivElement | null} */ (null),
				);
				const [isRoot, setIsRoot] = useState(false);
				const [topLevelIndex, setTopLevelIndex] = useState(
					/** @type {number | undefined} */ (undefined),
				);
				const islands = ArrowAtomBlock.islands.use();
				const [globalSsrContainers, setGlobalSsrContainers] =
					ArrowAtomBlock.ssrContainers.use();
				const ssrContent =
					islands && topLevelIndex !== undefined ?
						islands[topLevelIndex]
					:	'';

				useLayoutEffect(() => {
					const { current: container } = containerRef;
					if (!container) return;

					const isRoot =
						!container.parentElement?.closest('[data-aa]');
					setIsRoot(isRoot);
				}, []);

				useLayoutEffect(() => {
					if (!isRoot) return;

					const { current: ssrContainer } = ssrContainerRef;
					if (!ssrContainer) return;

					const ssrContainers = [
						.../** @type {DocumentFragment} */ (
							ssrContainer.getRootNode()
						).querySelectorAll('[data-aa-ssr]'),
					];
					if (ssrContainers.length <= 0) return;

					setGlobalSsrContainers((globalSsrContainers) => {
						const shouldUpdate =
							globalSsrContainers.length !==
								ssrContainers.length ||
							ssrContainers.some(
								(v, i) => globalSsrContainers[i] !== v,
							);
						if (!shouldUpdate) return globalSsrContainers;

						return ssrContainers;
					});
				}, [isRoot]);

				useLayoutEffect(() => {
					if (!isRoot) return;

					const { current: ssrContainer } = ssrContainerRef;
					if (!ssrContainer) return;

					const topLevelIndex =
						globalSsrContainers.indexOf(ssrContainer);
					setTopLevelIndex(topLevelIndex);
				}, [isRoot, globalSsrContainers]);

				// 0. start an api call (using `wp.apiFetch`) to `/aa/v1/render/islands/post/...` (https://wordpress.stackexchange.com/questions/320676/how-do-i-get-the-current-post-id-within-a-gutenberg-block-editor-block)
				// 1. check current aa block is a inner block of a aa block, if so, don't return a `data-aa-ssr` element
				// 2. when the api call resolves & `data-aa-ssr` elements are mounted, replace their children with the response, based on their index

				return x(
					'div',
					{
						'data-aa': '',
						style: { display: 'contents' },
						ref: containerRef,
					},
					[
						isRoot &&
							x(
								'div',
								{
									'data-aa-ssr': '',
									ref: ssrContainerRef,
									style: {
										display: 'contents',
									},
									dangerouslySetInnerHTML: {
										// eslint-disable-next-line @typescript-eslint/naming-convention
										__html: ssrContent,
									},
								},
								null,
							),
						x(
							'div',
							{
								'data-aa-source': '',
								style: { display: 'none' },
							},
							[
								x(RawHTML, { children: content }),
								x(Fragment, {
									children: innerBlocksProps.children ?? [],
								}),
							],
						),
					],
				);
			}

			save(/** @type {BlockSaveProps<T>} */ { attributes: { content } }) {
				const innerBlocksProps =
					/** @type {{ children: ReactNode }} */ (
						useInnerBlocksProps.save()
					);

				return x(Fragment, {}, [
					x(RawHTML, { children: content }),
					x(Fragment, { children: innerBlocksProps.children ?? [] }),
				]);
			}
		};

		globalThis.ArrowAtomBlock = ArrowAtomBlock;
	}
}
