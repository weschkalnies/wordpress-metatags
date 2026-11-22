/* global wp, metatagsEditor */
( function ( wp ) {
	'use strict';

	var editorApi = wp && wp.editor && wp.editor.PluginSidebar ? wp.editor : ( wp && wp.editPost );
	if ( ! editorApi || ! editorApi.PluginSidebar || ! wp.plugins || ! wp.components || ! wp.data || ! wp.element ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useRef = wp.element.useRef;
	var registerPlugin = wp.plugins.registerPlugin;
	var PluginSidebar = editorApi.PluginSidebar;
	var PluginSidebarMoreMenuItem = editorApi.PluginSidebarMoreMenuItem;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var ToggleControl = wp.components.ToggleControl;
	var Button = wp.components.Button;
	var BaseControl = wp.components.BaseControl;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;

	var EMPTY_OBJ = {};
	var config = window.metatagsEditor || {};
	var settings = config.settings || {};
	var i18n = config.i18n || {};

	var icon = el(
		'svg',
		{ width: 20, height: 20, viewBox: '0 0 20 20', fill: 'none', xmlns: 'http://www.w3.org/2000/svg' },
		el( 'path', {
			d: 'M3 4.5A1.5 1.5 0 0 1 4.5 3h4.88a1.5 1.5 0 0 1 1.06.44l6.12 6.12a1.5 1.5 0 0 1 0 2.12l-4.88 4.88a1.5 1.5 0 0 1-2.12 0L3.44 10.44A1.5 1.5 0 0 1 3 9.38V4.5Z',
			stroke: 'currentColor',
			strokeWidth: 1.5,
			strokeLinejoin: 'round',
		} ),
		el( 'circle', { cx: 6.75, cy: 6.75, r: 1.25, fill: 'currentColor' } )
	);

	function plainText( html, maxLen ) {
		var text = String( html || '' )
			.replace( /<!--[\s\S]*?-->/g, '' )
			.replace( /\[\/?[a-zA-Z0-9_-]+(?:\s[^\]]*)?\]/g, '' );
		var doc = new DOMParser().parseFromString( text, 'text/html' );
		text = ( doc && doc.body ? doc.body.textContent : '' ) || '';
		text = text.replace( /\s+/g, ' ' ).trim();
		if ( maxLen > 3 && text.length > maxLen ) {
			text = text.slice( 0, maxLen - 3 ).replace( /\s+\S*$/, '' ) + '…';
		}
		return text;
	}

	function splitRobots( value ) {
		var tokens = String( value || '' ).toLowerCase().split( /[\s,]+/ ).filter( Boolean );
		var noindex = false;
		var nofollow = false;
		var extra = [];

		tokens.forEach( function ( token ) {
			if ( 'none' === token ) {
				noindex = true;
				nofollow = true;
			} else if ( 'noindex' === token ) {
				noindex = true;
			} else if ( 'nofollow' === token ) {
				nofollow = true;
			} else {
				extra.push( token );
			}
		} );

		if ( noindex ) {
			extra = extra.filter( function ( t ) { return 'index' !== t; } );
		}
		if ( nofollow ) {
			extra = extra.filter( function ( t ) { return 'follow' !== t; } );
		}

		return {
			noindex: noindex,
			nofollow: nofollow,
			extra: extra.join( ', ' ),
		};
	}

	function combineRobots( noindex, nofollow, extraRaw ) {
		var parts = [];
		var tokens = String( extraRaw || '' ).split( /[\s,]+/ ).filter( Boolean );

		if ( noindex ) {
			parts.push( 'noindex' );
		}
		if ( nofollow ) {
			parts.push( 'nofollow' );
		}

		tokens.forEach( function ( token ) {
			var lower = token.toLowerCase();
			if ( 'noindex' === lower || 'nofollow' === lower || 'none' === lower ) {
				return;
			}
			if ( noindex && 'index' === lower ) {
				return;
			}
			if ( nofollow && 'follow' === lower ) {
				return;
			}
			parts.push( token );
		} );

		return parts.join( ', ' );
	}

	function renderCounter( value, placeholder, max ) {
		var len = ( value || placeholder || '' ).length;
		var cls = 'metatags-counter';
		if ( len > max ) {
			cls += ' is-too-long';
		}
		if ( ! value ) {
			cls += ' is-placeholder';
		}
		var label = ( i18n.counter || '%1$d / %2$d' )
			.replace( '%1$d', String( len ) )
			.replace( '%2$d', String( max ) );
		return el( 'span', { className: cls, 'aria-live': 'polite' }, label );
	}

	function MetatagsSidebar() {
		var frameRef = useRef( null );
		var extraState = useState( null );
		var extraDraft = extraState[ 0 ];
		var setExtraDraft = extraState[ 1 ];

		var data = useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			var meta = editor.getEditedPostAttribute( 'meta' ) || EMPTY_OBJ;
			var title = editor.getEditedPostAttribute( 'title' ) || '';
			var excerpt = editor.getEditedPostAttribute( 'excerpt' ) || '';
			var content = '';
			if ( ! excerpt ) {
				content = editor.getEditedPostContent
					? ( editor.getEditedPostContent() || '' )
					: ( editor.getEditedPostAttribute( 'content' ) || '' );
			}
			var link = editor.getPermalink
				? ( editor.getPermalink() || '' )
				: ( editor.getEditedPostAttribute( 'link' ) || '' );
			var status = editor.getEditedPostAttribute( 'status' ) || '';
			var mediaId = editor.getEditedPostAttribute( 'featured_media' ) || 0;
			var media = mediaId ? select( 'core' ).getEntityRecord( 'postType', 'attachment', mediaId ) : null;
			var mediaUrl = '';

			if ( media ) {
				var sizes = media.media_details && media.media_details.sizes ? media.media_details.sizes : null;
				if ( sizes && sizes[ '1536x1536' ] && sizes[ '1536x1536' ].source_url ) {
					mediaUrl = sizes[ '1536x1536' ].source_url;
				} else if ( sizes && sizes.full && sizes.full.source_url ) {
					mediaUrl = sizes.full.source_url;
				} else if ( media.source_url ) {
					mediaUrl = media.source_url;
				}
			}

			return {
				meta: meta,
				title: title,
				excerpt: excerpt,
				content: content,
				link: link,
				status: status,
				mediaUrl: mediaUrl,
			};
		}, [] );

		var editPost = useDispatch( 'core/editor' ).editPost;

		function updateMeta( patch ) {
			editPost( { meta: patch } );
		}

		var meta = data.meta;
		var fallbacks = !! settings.fallbacks;
		var liveTitle = ( fallbacks && 'auto-draft' !== data.status ) ? plainText( data.title, 0 ) : '';
		var liveExcerpt = fallbacks ? plainText( data.excerpt || data.content, 160 ) : '';
		var liveLink = ( fallbacks && 'publish' === data.status ) ? data.link : '';
		var fallbackOgImage = fallbacks ? ( data.mediaUrl || config.defaultOgImage || '' ) : '';

		var robotsParts = splitRobots( meta._metaRobots );
		var hasSearchFields = settings.title || settings.description || settings.canonical || settings.robots || settings.keywords || settings.custom;
		var hasAnyPanel = hasSearchFields || settings.og;

		function openMediaFrame() {
			if ( ! frameRef.current && wp.media ) {
				frameRef.current = wp.media( {
					title: i18n.chooseImage || 'Choose Open Graph image',
					button: { text: i18n.useImage || 'Use this image' },
					library: { type: 'image' },
					multiple: false,
				} );
				frameRef.current.on( 'select', function () {
					var attachment = frameRef.current.state().get( 'selection' ).first().toJSON();
					var sizes = attachment.sizes || {};
					var chosen = ( sizes.large && sizes.large.width >= 1200 ) ? sizes.large : ( sizes.full || attachment );
					updateMeta( {
						_metaOGImage: chosen.url || '',
						_metaOGImageId: attachment.id || 0,
					} );
				} );
			}
			if ( frameRef.current ) {
				frameRef.current.open();
			}
		}

		var ogPreviewUrl = meta._metaOGImage || fallbackOgImage;

		var searchPanel = hasSearchFields && el(
			PanelBody,
			{ title: i18n.searchEngines || 'Search engines', initialOpen: true },
			settings.title && el(
				'div',
				{ className: 'metatags-sidebar-field' },
				el( TextControl, {
					label: i18n.title || 'Title',
					value: meta._metaTitle || '',
					placeholder: liveTitle,
					onChange: function ( val ) { updateMeta( { _metaTitle: val } ); },
					__nextHasNoMarginBottom: true,
				} ),
				renderCounter( meta._metaTitle || '', liveTitle, 60 )
			),
			settings.description && el(
				'div',
				{ className: 'metatags-sidebar-field' },
				el( TextareaControl, {
					label: i18n.description || 'Description',
					value: meta._metaDesc || '',
					placeholder: liveExcerpt,
					rows: 3,
					onChange: function ( val ) { updateMeta( { _metaDesc: val } ); },
					__nextHasNoMarginBottom: true,
				} ),
				renderCounter( meta._metaDesc || '', liveExcerpt, 160 )
			),
			settings.canonical && el( TextControl, {
				label: i18n.canonical || 'Canonical URL',
				type: 'url',
				value: meta._metaCanonical || '',
				placeholder: liveLink,
				help: i18n.canonicalHelp,
				onChange: function ( val ) { updateMeta( { _metaCanonical: val } ); },
				__nextHasNoMarginBottom: true,
			} ),
			settings.robots && el(
				Fragment,
				null,
				el( ToggleControl, {
					label: i18n.noindex || 'Discourage indexing (noindex)',
					checked: robotsParts.noindex,
					onChange: function ( checked ) {
						var extra = null !== extraDraft ? extraDraft : robotsParts.extra;
						updateMeta( { _metaRobots: combineRobots( checked, robotsParts.nofollow, extra ) } );
					},
					__nextHasNoMarginBottom: true,
				} ),
				el( ToggleControl, {
					label: i18n.nofollow || 'Do not follow links (nofollow)',
					checked: robotsParts.nofollow,
					onChange: function ( checked ) {
						var extra = null !== extraDraft ? extraDraft : robotsParts.extra;
						updateMeta( { _metaRobots: combineRobots( robotsParts.noindex, checked, extra ) } );
					},
					__nextHasNoMarginBottom: true,
				} ),
				el( TextControl, {
					label: i18n.moreDirectives || 'More directives',
					value: null !== extraDraft ? extraDraft : robotsParts.extra,
					help: i18n.moreHelp,
					onChange: function ( val ) {
						setExtraDraft( val );
						updateMeta( { _metaRobots: combineRobots( robotsParts.noindex, robotsParts.nofollow, val ) } );
					},
					onBlur: function () { setExtraDraft( null ); },
					__nextHasNoMarginBottom: true,
				} )
			),
			settings.keywords && el( TextControl, {
				label: i18n.keywords || 'Keywords',
				value: meta._metaKeywords || '',
				help: i18n.keywordsHelp,
				onChange: function ( val ) { updateMeta( { _metaKeywords: val } ); },
				__nextHasNoMarginBottom: true,
			} ),
			settings.custom && el( 'p', { className: 'components-base-control__help' }, i18n.customHint )
		);

		var ogTitlePlaceholder = ( settings.title && meta._metaTitle ) ? meta._metaTitle : liveTitle;
		var ogDescPlaceholder = ( settings.description && meta._metaDesc ) ? meta._metaDesc : liveExcerpt;
		var ogUrlPlaceholder = ( settings.canonical && meta._metaCanonical ) ? meta._metaCanonical : liveLink;

		var ogPanel = settings.og && el(
			PanelBody,
			{ title: i18n.socialMedia || 'Social media (Open Graph)', initialOpen: true },
			el(
				'div',
				{ className: 'metatags-sidebar-field' },
				el( TextControl, {
					label: i18n.title || 'Title',
					value: meta._metaOGTitle || '',
					placeholder: ogTitlePlaceholder,
					onChange: function ( val ) { updateMeta( { _metaOGTitle: val } ); },
					__nextHasNoMarginBottom: true,
				} ),
				renderCounter( meta._metaOGTitle || '', ogTitlePlaceholder, 60 )
			),
			el(
				'div',
				{ className: 'metatags-sidebar-field' },
				el( TextareaControl, {
					label: i18n.description || 'Description',
					value: meta._metaOGDesc || '',
					placeholder: ogDescPlaceholder,
					rows: 3,
					onChange: function ( val ) { updateMeta( { _metaOGDesc: val } ); },
					__nextHasNoMarginBottom: true,
				} ),
				renderCounter( meta._metaOGDesc || '', ogDescPlaceholder, 200 )
			),
			el(
				BaseControl,
				{ label: i18n.image || 'Image', help: i18n.imageHelp, __nextHasNoMarginBottom: true },
				ogPreviewUrl && el( 'img', {
					className: 'metatags-sidebar-image__preview',
					src: ogPreviewUrl,
					alt: '',
				} ),
				el( TextControl, {
					type: 'url',
					value: meta._metaOGImage || '',
					placeholder: fallbackOgImage,
					onChange: function ( val ) {
						updateMeta( { _metaOGImage: val, _metaOGImageId: 0 } );
					},
					__nextHasNoMarginBottom: true,
				} ),
				el(
					'div',
					{ className: 'metatags-sidebar-image__actions' },
					el(
						Button,
						{ variant: 'secondary', onClick: openMediaFrame },
						i18n.selectImage || 'Select image'
					),
					meta._metaOGImage && el(
						Button,
						{
							variant: 'link',
							isDestructive: true,
							onClick: function () {
								updateMeta( { _metaOGImage: '', _metaOGImageId: 0 } );
							},
						},
						i18n.remove || 'Remove'
					)
				)
			),
			el( TextControl, {
				label: i18n.ogType || 'Type',
				value: meta._metaOGType || '',
				placeholder: fallbacks ? ( config.defaultOgType || 'website' ) : '',
				help: i18n.ogTypeHelp,
				onChange: function ( val ) { updateMeta( { _metaOGType: val } ); },
				__nextHasNoMarginBottom: true,
			} ),
			el( TextControl, {
				label: i18n.ogUrl || 'URL',
				type: 'url',
				value: meta._metaOGURL || '',
				placeholder: ogUrlPlaceholder,
				help: i18n.ogUrlHelp,
				onChange: function ( val ) { updateMeta( { _metaOGURL: val } ); },
				__nextHasNoMarginBottom: true,
			} ),
			el( TextControl, {
				label: i18n.ogSiteName || 'Site name',
				value: meta._metaOGSiteName || '',
				placeholder: fallbacks ? ( config.siteName || '' ) : '',
				onChange: function ( val ) { updateMeta( { _metaOGSiteName: val } ); },
				__nextHasNoMarginBottom: true,
			} )
		);

		return el(
			Fragment,
			null,
			el(
				PluginSidebarMoreMenuItem,
				{ target: 'metatags-sidebar', icon: icon },
				i18n.panelTitle || 'Meta Tags'
			),
			el(
				PluginSidebar,
				{
					name: 'metatags-sidebar',
					title: i18n.panelTitle || 'Meta Tags',
					icon: icon,
				},
				searchPanel,
				ogPanel,
				! hasAnyPanel && el(
					'p',
					{ className: 'metatags-sidebar-note' },
					i18n.allDisabled,
					' ',
					el( 'a', { href: config.settingsUrl }, i18n.openSettings )
				),
				hasAnyPanel && fallbacks && el(
					'p',
					{ className: 'metatags-sidebar-note' },
					i18n.placeholderHint
				)
			)
		);
	}

	registerPlugin( 'metatags', { render: MetatagsSidebar } );
} )( window.wp );
