/**
 * Wikilink Gutenberg Format
 *
 * Inserts wikilink shortcodes using WordPress LinkControl for native UX
 */
(function (wp) {
	const { insert, create, useAnchor } = wp.richText;
	const { BlockControls, LinkControl } = wp.blockEditor;
	const { Popover, ToolbarButton } = wp.components;
	const { useState, createElement } = wp.element;
	const { __ } = wp.i18n;

	const el = createElement;
	const FORMAT_NAME = "bleikoya/wikilink";

	// SVG icon for toolbar button
	function WikilinkIcon(props) {
		var size = props.size || 16;
		return el(
			"svg",
			{
				xmlns: "http://www.w3.org/2000/svg",
				width: size,
				height: size,
				viewBox: "0 0 24 24",
				fill: "none",
				stroke: "currentColor",
				strokeWidth: "2",
				strokeLinecap: "round",
				strokeLinejoin: "round",
			},
			el("path", {
				d: "M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71",
			})
		);
	}

	// Wikilink inserter component using LinkControl
	function WikilinkInserter(props) {
		var value = props.value;
		var onChange = props.onChange;
		var contentRef = props.contentRef;

		var _isOpen = useState(false);
		var isOpen = _isOpen[0];
		var setIsOpen = _isOpen[1];

		// Get anchor for popover positioning
		var popoverAnchor = useAnchor({
			editableContentElement: contentRef.current,
			settings: {
				name: FORMAT_NAME,
				tagName: "span",
				className: "b-wikilink-format",
			},
		});

		// Insert wikilink shortcode into content
		function insertWikilink(result) {
			var start = value.start;
			var end = value.end;
			var text = value.text;
			var selectedText = text.substring(start, end);

			// Build shortcode
			var shortcode;
			if (selectedText && selectedText.length > 0 && selectedText !== result.title) {
				// Custom text - use text attribute
				shortcode = '[wikilink to="' + result.reference + '" text="' + selectedText + '"]';
			} else {
				// No selection or same as title - just use reference
				shortcode = '[wikilink to="' + result.reference + '"]';
			}

			// Insert shortcode as plain text
			var toInsert = create({ text: shortcode });
			var newValue = insert(value, toInsert, start, end);

			onChange(newValue);
			setIsOpen(false);
		}

		// Custom search function for LinkControl
		async function fetchSearchSuggestions(search) {
			if (!search || search.length < 2) {
				return [];
			}

			try {
				var response = await wp.apiFetch({
					path: "/bleikoya/v1/wikilink-search?query=" + encodeURIComponent(search),
				});

				return (response.results || []).map(function (result) {
					return {
						id: result.reference, // Use reference as id (type:id format)
						type: result.type,
						title: result.title,
						url: "#wikilink:" + result.reference, // Prefix to identify our results
						kind: "custom",
					};
				});
			} catch (error) {
				return [];
			}
		}

		// Handle selection from LinkControl
		function handleLinkChange(nextValue) {
			if (nextValue && nextValue.id) {
				// Build reference from type and id
				var reference = null;

				// Map WordPress post types to our wikilink types
				var typeMap = {
					'kartpunkt': 'location',
					'tribe_events': 'event',
					'post': 'post',
					'page': 'page',
					'user': 'user',
					'category': 'category'
				};

				var type = nextValue.type ? typeMap[nextValue.type] || nextValue.type : null;

				if (type && nextValue.id) {
					reference = type + ':' + nextValue.id;
				} else if (nextValue.id) {
					// Fallback to just id if no type
					reference = String(nextValue.id);
				}

				if (reference) {
					insertWikilink({
						reference: reference,
						title: nextValue.title || "",
					});
				}
			}
		}

		// Toggle popover
		function togglePopover() {
			setIsOpen(!isOpen);
		}

		// Build popover with LinkControl
		var popoverContent = null;
		if (isOpen) {
			popoverContent = el(
				Popover,
				{
					anchor: popoverAnchor,
					placement: "bottom-start",
					onClose: function () {
						setIsOpen(false);
					},
					className: "wikilink-popover",
					focusOnMount: "firstElement",
				},
				el(LinkControl, {
					searchInputPlaceholder: __("Søk etter innhold...", "bleikoya"),
					value: null,
					showInitialSuggestions: false,
					noDirectEntry: true,
					noURLSuggestion: true,
					suggestionsQuery: {},
					fetchSearchSuggestions: fetchSearchSuggestions,
					onChange: handleLinkChange,
					onRemove: function () {
						setIsOpen(false);
					},
					settings: [],
				})
			);
		}

		return el(
			wp.element.Fragment,
			null,
			el(
				BlockControls,
				{ group: "inline" },
				el(ToolbarButton, {
					icon: el(WikilinkIcon, { size: 20 }),
					title: __("Wikilink", "bleikoya"),
					onClick: togglePopover,
					isActive: isOpen,
				})
			),
			popoverContent
		);
	}

	// Register as a format to get the toolbar button in rich text
	wp.richText.registerFormatType("bleikoya/wikilink", {
		title: __("Wikilink", "bleikoya"),
		tagName: "span",
		className: "b-wikilink-format",
		edit: WikilinkInserter,
	});
})(window.wp);
