<?php
/**
 * Template for displaying search forms in Twenty Seventeen
 *
 * @package WordPress
 * @subpackage NRKSessions
 * @since 1.0
 * @version 1.0
 */

?>

<form class="nrkmusikk-search-form" action="search" method="get" action="/" role="search">
	<label for="nrkmusikk-search"></label>
	<input class="nrkmusikk-search-field"
		name="s" id="nrkmusikk-search"
		type="text" placeholder="Søk blant artister, låter og sjangre..."
		value="<?php echo get_search_query() ?>"
		autocomplete="off"
		aria-autocomplete="list"
		aria-expanded="true" />
	<core-suggest  ajax="/search/{{value}}?ajax">
	</core-suggest>

	<button type="submit" aria-label="Søk"><svg viewBox="0 0 15 15" width="1.500em" height="1.500em" aria-hidden="true" focusable="false"><path d="M7 12a5 5 0 1 0-.01-10.01 5 5 0 0 0 .01 10zm0 1A6 6 0 1 1 7.01.99 6 6 0 0 1 7 13zm3.94-1.21l.87-.87 2 2c.29.3.29.58 0 .87-.29.29-.58.29-.86 0l-2-2z"></path></svg></button>
</form>
