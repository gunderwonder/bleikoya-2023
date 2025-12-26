<?php
/**
 * Template Name: Stilguide
 * Template Post Type: page
 *
 * Stilguide for Bleikøya-temaet
 * Tilgjengelig på /stilguide når en side med den slug-en opprettes
 */

// Begrens tilgang til innloggede brukere
if (!is_user_logged_in()) {
	wp_redirect(wp_login_url(get_permalink()));
	exit;
}

get_header();
?>

<style>
/* Stilguide-spesifikke stiler */
.sg-nav {
	position: sticky;
	top: 0;
	background: white;
	padding: 1rem;
	border-bottom: 1px solid var(--b-border-color);
	z-index: 100;
	display: flex;
	flex-wrap: wrap;
	gap: 0.5rem;
	justify-content: center;
}
.admin-bar .sg-nav {
	top: var(--wp-admin--admin-bar--height, 32px);
}
.sg-nav a {
	font-size: 0.75rem;
	border: none;
}
.sg-section {
	padding: 2rem 0;
	border-bottom: 1px solid var(--b-border-color);
}
.sg-section:last-child {
	border-bottom: none;
}
.sg-color-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
	gap: 1rem;
}
.sg-color-swatch {
	border-radius: 8px;
	overflow: hidden;
	border: 1px solid var(--b-border-color);
}
.sg-color-swatch__color {
	height: 80px;
}
.sg-color-swatch__info {
	padding: 0.5rem;
	font-size: 0.8rem;
}
.sg-color-swatch__name {
	font-weight: bold;
	font-family: monospace;
}
.sg-code {
	background: #f5f5f5;
	padding: 1rem;
	border-radius: 8px;
	overflow-x: auto;
	margin-top: 1rem;
	font-size: 0.85rem;
}
.sg-code code {
	font-family: 'SF Mono', Monaco, 'Courier New', monospace;
	white-space: pre;
}
.sg-example {
	padding: 2rem;
	background: #fafafa;
	border-radius: 8px;
	margin-bottom: 1rem;
}
.sg-example--white {
	background: white;
	border: 1px solid var(--b-border-color);
}
.sg-label {
	font-size: 0.7rem;
	text-transform: uppercase;
	color: var(--b-header-color);
	margin-bottom: 0.5rem;
	font-weight: bold;
}
.sg-component {
	margin-bottom: 2rem;
}
</style>

<nav class="sg-nav">
	<a href="#farger" class="b-button">Farger</a>
	<a href="#typografi" class="b-button">Typografi</a>
	<a href="#layout" class="b-button">Layout</a>
	<a href="#knapper" class="b-button">Knapper</a>
	<a href="#bokser" class="b-button">Bokser</a>
	<a href="#quicklinks" class="b-button">Quicklinks</a>
	<a href="#artikler" class="b-button">Artikler</a>
	<a href="#hendelser" class="b-button">Hendelser</a>
	<a href="#author" class="b-button">Hytteeier</a>
	<a href="#emner" class="b-button">Emner</a>
	<a href="#skjema" class="b-button">Skjema</a>
	<a href="#lister" class="b-button">Lister</a>
</nav>

<div class="b-center-wide">

	<!-- FARGER -->
	<section id="farger" class="sg-section">
		<h1>Fargepalett</h1>
		<p class="b-body-text">CSS-variabler definert i <code>:root</code></p>

		<h2>Hovedfarger</h2>
		<div class="sg-color-grid">
			<div class="sg-color-swatch">
				<div class="sg-color-swatch__color" style="background-color: var(--b-primary-color);"></div>
				<div class="sg-color-swatch__info">
					<div class="sg-color-swatch__name">--b-primary-color</div>
					<div>#b93e3c (rød)</div>
				</div>
			</div>
			<div class="sg-color-swatch">
				<div class="sg-color-swatch__color" style="background-color: var(--b-green-color);"></div>
				<div class="sg-color-swatch__info">
					<div class="sg-color-swatch__name">--b-green-color</div>
					<div>rgb(81, 131, 71)</div>
				</div>
			</div>
			<div class="sg-color-swatch">
				<div class="sg-color-swatch__color" style="background-color: var(--b-blue-color);"></div>
				<div class="sg-color-swatch__info">
					<div class="sg-color-swatch__name">--b-blue-color</div>
					<div>rgb(90, 146, 203)</div>
				</div>
			</div>
			<div class="sg-color-swatch">
				<div class="sg-color-swatch__color" style="background-color: var(--b-blue-dark-color);"></div>
				<div class="sg-color-swatch__info">
					<div class="sg-color-swatch__name">--b-blue-dark-color</div>
					<div>rgb(55, 105, 160) — WCAG AA</div>
				</div>
			</div>
			<div class="sg-color-swatch">
				<div class="sg-color-swatch__color" style="background-color: var(--b-yellow-color);"></div>
				<div class="sg-color-swatch__info">
					<div class="sg-color-swatch__name">--b-yellow-color</div>
					<div>rgb(232, 195, 103)</div>
				</div>
			</div>
		</div>

		<h2>Nøytrale farger</h2>
		<div class="sg-color-grid">
			<div class="sg-color-swatch">
				<div class="sg-color-swatch__color" style="background-color: var(--b-header-color);"></div>
				<div class="sg-color-swatch__info">
					<div class="sg-color-swatch__name">--b-header-color</div>
					<div>rgb(70, 70, 70)</div>
				</div>
			</div>
			<div class="sg-color-swatch">
				<div class="sg-color-swatch__color" style="background-color: var(--b-black-color);"></div>
				<div class="sg-color-swatch__info">
					<div class="sg-color-swatch__name">--b-black-color</div>
					<div>rgb(0, 0, 0)</div>
				</div>
			</div>
			<div class="sg-color-swatch">
				<div class="sg-color-swatch__color" style="background-color: var(--b-border-color);"></div>
				<div class="sg-color-swatch__info">
					<div class="sg-color-swatch__name">--b-border-color</div>
					<div>rgb(230, 230, 233)</div>
				</div>
			</div>
		</div>

		<h2>Transparente varianter</h2>
		<div class="sg-color-grid">
			<div class="sg-color-swatch">
				<div class="sg-color-swatch__color" style="background-color: var(--b-red-transparent-color);"></div>
				<div class="sg-color-swatch__info">
					<div class="sg-color-swatch__name">--b-red-transparent-color</div>
					<div>rgba(185, 62, 60, 0.05)</div>
				</div>
			</div>
			<div class="sg-color-swatch">
				<div class="sg-color-swatch__color" style="background-color: var(--b-green-transparent-color);"></div>
				<div class="sg-color-swatch__info">
					<div class="sg-color-swatch__name">--b-green-transparent-color</div>
					<div>rgba(81, 131, 71, 0.05)</div>
				</div>
			</div>
			<div class="sg-color-swatch">
				<div class="sg-color-swatch__color" style="background-color: var(--b-blue-transparent-color);"></div>
				<div class="sg-color-swatch__info">
					<div class="sg-color-swatch__name">--b-blue-transparent-color</div>
					<div>rgba(90, 146, 203, 0.05)</div>
				</div>
			</div>
			<div class="sg-color-swatch">
				<div class="sg-color-swatch__color" style="background-color: var(--b-yellow-transparent-color);"></div>
				<div class="sg-color-swatch__info">
					<div class="sg-color-swatch__name">--b-yellow-transparent-color</div>
					<div>rgba(232, 195, 103, 0.3)</div>
				</div>
			</div>
		</div>
	</section>

	<!-- TYPOGRAFI -->
	<section id="typografi" class="sg-section">
		<h1>Typografi</h1>

		<div class="sg-component">
			<div class="sg-label">Overskrift h1 (Futura/Jost, 1.8rem, uppercase)</div>
			<div class="sg-example--white sg-example">
				<h1 style="margin:0;">Dette er en hovedoverskrift</h1>
			</div>
			<div class="sg-code"><code>&lt;h1&gt;Dette er en hovedoverskrift&lt;/h1&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">Overskrift h2 (Futura/Jost, 1rem, uppercase, bold)</div>
			<div class="sg-example--white sg-example">
				<h2 style="margin:0;">Dette er en underoverskrift</h2>
			</div>
			<div class="sg-code"><code>&lt;h2&gt;Dette er en underoverskrift&lt;/h2&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">Overskrift h3 (Futura/Jost, 1rem, normal)</div>
			<div class="sg-example--white sg-example">
				<h3 style="margin:0;">Dette er en tredje nivå overskrift</h3>
			</div>
			<div class="sg-code"><code>&lt;h3&gt;Dette er en tredje nivå overskrift&lt;/h3&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">Brødtekst (Libre Franklin, line-height 1.7em)</div>
			<div class="sg-example--white sg-example">
				<div class="b-body-text">
					<p>Dette er et eksempel på brødtekst. Bleikøya er en liten øy i Oslofjorden som har vært et populært feriested i over hundre år. Her kan du lese mer om øyas historie og tradisjoner.</p>
					<p>Andre avsnitt fortsetter her med mer informasjon. <a href="#">Dette er en lenke</a> som viser hvordan lenker ser ut i brødtekst.</p>
				</div>
			</div>
			<div class="sg-code"><code>&lt;div class="b-body-text"&gt;
  &lt;p&gt;Brødtekst her...&lt;/p&gt;
  &lt;p&gt;Med &lt;a href="#"&gt;lenker&lt;/a&gt; som dette.&lt;/p&gt;
&lt;/div&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">Lenker</div>
			<div class="sg-example--white sg-example">
				<p><a href="#">Standard lenke med understrek</a></p>
			</div>
			<div class="sg-code"><code>&lt;a href="#"&gt;Standard lenke med understrek&lt;/a&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">Permalink / dato (0.8rem, uppercase)</div>
			<div class="sg-example--white sg-example">
				<a class="b-article-permalink" href="#"><?php echo date_i18n('j. F Y'); ?></a>
			</div>
			<div class="sg-code"><code>&lt;a class="b-article-permalink" href="#"&gt;Dato&lt;/a&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">Løpende brødtekst (komplett eksempel)</div>
			<div class="sg-example--white sg-example">
				<div class="b-body-text">
					<p>Bleikøya er en liten øy i Oslofjorden som har vært et populært feriested i over hundre år. Øya har en rik historie og mange tradisjoner som fortsatt holdes i hevd av beboerne.</p>

					<h2>Øyas historie</h2>
					<p>De første hyttene ble bygget på slutten av 1800-tallet, og siden den gang har øya vært et kjært feriested for mange familier. Les mer om <a href="#">øyas fascinerende historie</a> i vårt arkiv.</p>

					<h3>Viktige årstall</h3>
					<p>Her er noen viktige milepæler i øyas utvikling:</p>

					<ul>
						<li>1890: De første hyttene bygges</li>
						<li>1920: Velforeningen stiftes</li>
						<li>1950: Strøm legges inn på øya</li>
						<li>2000: Ny brygge ferdigstilles</li>
					</ul>

					<h3>Praktisk informasjon</h3>
					<p>For å komme til øya følger du disse stegene:</p>

					<ol>
						<li>Ta buss eller trikk til Aker brygge</li>
						<li>Gå til båtterminalen ved Rådhusbrygge 4</li>
						<li>Ta Nesoddbåten og gå av på Bleikøya</li>
					</ol>

					<figure>
						<img src="https://placehold.co/600x300/518347/white?text=Bleik%C3%B8ya" alt="Utsikt over Bleikøya">
						<figcaption>Utsikt over Bleikøya en sommerdag. Foto: Velforeningen</figcaption>
					</figure>

					<blockquote>
						<p>«Bleikøya er ikke bare et sted – det er en livsstil og et fellesskap som går i arv fra generasjon til generasjon.»</p>
					</blockquote>

					<h3>Nøkkeltall</h3>
					<table>
						<thead>
							<tr>
								<th>Kategori</th>
								<th>Antall</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td>Hytter</td>
								<td>91</td>
							</tr>
							<tr>
								<td>Brygger</td>
								<td>4</td>
							</tr>
							<tr>
								<td>Areal</td>
								<td>0,12 km²</td>
							</tr>
						</tbody>
					</table>

					<h3>Teknisk informasjon</h3>
					<p>For utviklere som jobber med nettsiden:</p>

					<pre><code>// Eksempel på API-kall
fetch('/wp-json/bleikoya/v1/locations')
  .then(response => response.json())
  .then(data => console.log(data));</code></pre>

					<h3>Ordliste</h3>
					<dl>
						<dt>Velforening</dt>
						<dd>En organisasjon som ivaretar fellesinteressene til beboerne i et område.</dd>
						<dt>Kartpunkt</dt>
						<dd>Et sted på kartet som kan kobles til annet innhold på nettsiden.</dd>
						<dt>Gruppe</dt>
						<dd>En kategori for kartpunkter, f.eks. "Hytter" eller "Brygger".</dd>
					</dl>

					<h3>Wikilenker</h3>
					<p>Wikilenker brukes for å lenke til internt innhold med fargekodede typer:</p>
					<p>
						<a href="#" class="b-wikilink b-wikilink--post"><i data-lucide="newspaper" class="b-wikilink__icon"></i><span class="b-wikilink__label">Oppslag</span></a>
						<a href="#" class="b-wikilink b-wikilink--page"><i data-lucide="file-text" class="b-wikilink__icon"></i><span class="b-wikilink__label">Side</span></a>
						<a href="#" class="b-wikilink b-wikilink--event"><i data-lucide="calendar" class="b-wikilink__icon"></i><span class="b-wikilink__label">Hendelse</span></a>
						<a href="#" class="b-wikilink b-wikilink--user"><i data-lucide="user" class="b-wikilink__icon"></i><span class="b-wikilink__label">Hytteeier</span></a>
						<a href="#" class="b-wikilink b-wikilink--location"><i data-lucide="map-pin" class="b-wikilink__icon"></i><span class="b-wikilink__label">Kartpunkt</span></a>
						<a href="#" class="b-wikilink b-wikilink--category"><i data-lucide="tag" class="b-wikilink__icon"></i><span class="b-wikilink__label">Kategori</span></a>
						<a href="#" class="b-wikilink b-wikilink--external"><i data-lucide="external-link" class="b-wikilink__icon"></i><span class="b-wikilink__label">Ekstern lenke</span></a>
						<span class="b-wikilink b-wikilink--missing"><i data-lucide="circle-help" class="b-wikilink__icon"></i><span class="b-wikilink__label">Mangler</span></span>
					</p>

					<hr>

					<p>For mer informasjon, ta kontakt med velforeningen.</p>
				</div>
			</div>
			<div class="sg-code"><code>&lt;div class="b-body-text"&gt;
  &lt;p&gt;Brødtekst...&lt;/p&gt;
  &lt;h2&gt;Underoverskrift&lt;/h2&gt;
  &lt;ul&gt;&lt;li&gt;Punktliste&lt;/li&gt;&lt;/ul&gt;
  &lt;ol&gt;&lt;li&gt;Nummerert liste&lt;/li&gt;&lt;/ol&gt;
  &lt;figure&gt;
    &lt;img src="..." alt="..."&gt;
    &lt;figcaption&gt;Bildetekst&lt;/figcaption&gt;
  &lt;/figure&gt;
  &lt;blockquote&gt;&lt;p&gt;Sitat&lt;/p&gt;&lt;/blockquote&gt;
  &lt;table&gt;...&lt;/table&gt;
  &lt;pre&gt;&lt;code&gt;Kodeblokk&lt;/code&gt;&lt;/pre&gt;
  &lt;dl&gt;&lt;dt&gt;Term&lt;/dt&gt;&lt;dd&gt;Definisjon&lt;/dd&gt;&lt;/dl&gt;
  &lt;hr&gt;

  &lt;!-- Wikilenker --&gt;
  &lt;a href="#" class="b-wikilink b-wikilink--post"&gt;
    &lt;i data-lucide="newspaper" class="b-wikilink__icon"&gt;&lt;/i&gt;
    &lt;span class="b-wikilink__label"&gt;Oppslag&lt;/span&gt;
  &lt;/a&gt;
  &lt;!-- Varianter: --post, --page, --event, --user,
       --location, --category, --external, --missing --&gt;
&lt;/div&gt;</code></div>
		</div>
	</section>

	<!-- LAYOUT -->
	<section id="layout" class="sg-section">
		<h1>Layout</h1>

		<div class="sg-component">
			<div class="sg-label">.b-center (max-width: 40rem)</div>
			<div class="sg-example">
				<div class="b-center" style="background: var(--b-blue-transparent-color); padding: 1rem;">
					Smal innholdscontainer (40rem)
				</div>
			</div>
			<div class="sg-code"><code>&lt;div class="b-center"&gt;
  Innhold her...
&lt;/div&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">.b-center-wide (max-width: 60rem)</div>
			<div class="sg-example">
				<div class="b-center-wide" style="background: var(--b-green-transparent-color); padding: 1rem;">
					Bred innholdscontainer (60rem)
				</div>
			</div>
			<div class="sg-code"><code>&lt;div class="b-center-wide"&gt;
  Innhold her...
&lt;/div&gt;</code></div>
		</div>
	</section>

	<!-- KNAPPER -->
	<section id="knapper" class="sg-section">
		<h1>Knapper</h1>

		<div class="sg-component">
			<div class="sg-label">.b-button (standard)</div>
			<div class="sg-example">
				<a href="#" class="b-button">Standard knapp</a>
				<a href="#" class="b-button b-button--active">Aktiv knapp</a>
			</div>
			<div class="sg-code"><code>&lt;a href="#" class="b-button"&gt;Standard knapp&lt;/a&gt;
&lt;a href="#" class="b-button b-button--active"&gt;Aktiv knapp&lt;/a&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">.b-button--yellow</div>
			<div class="sg-example">
				<a href="#" class="b-button b-button--yellow">Gul knapp</a>
				<a href="#" class="b-button b-button--yellow b-button--active">Aktiv gul</a>
			</div>
			<div class="sg-code"><code>&lt;a href="#" class="b-button b-button--yellow"&gt;Gul knapp&lt;/a&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">.b-button--green</div>
			<div class="sg-example">
				<a href="#" class="b-button b-button--green">Grønn knapp</a>
				<a href="#" class="b-button b-button--green b-button--active">Aktiv grønn</a>
			</div>
			<div class="sg-code"><code>&lt;a href="#" class="b-button b-button--green"&gt;Grønn knapp&lt;/a&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">.b-button--blue</div>
			<div class="sg-example">
				<a href="#" class="b-button b-button--blue">Blå knapp</a>
				<a href="#" class="b-button b-button--blue b-button--active">Aktiv blå</a>
			</div>
			<div class="sg-code"><code>&lt;a href="#" class="b-button b-button--blue"&gt;Blå knapp&lt;/a&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">.b-button--disabled</div>
			<div class="sg-example">
				<button class="b-button b-button--disabled">Deaktivert</button>
				<button class="b-button" disabled>Deaktivert (native)</button>
			</div>
			<div class="sg-code"><code>.b-button--disabled</code> eller <code>disabled</code>-attributt</div>
		</div>

		<div class="sg-component">
			<div class="sg-label">.b-button--sm (kompakt)</div>
			<div class="sg-example">
				<a href="#" class="b-button b-button--sm">Liten knapp</a>
				<a href="#" class="b-button b-button--sm b-button--yellow">Liten gul</a>
				<a href="#" class="b-button b-button--sm b-button--green">Liten grønn</a>
				<a href="#" class="b-button b-button--sm b-button--blue">Liten blå</a>
			</div>
			<div class="sg-code"><code>&lt;a href="#" class="b-button b-button--sm"&gt;Liten knapp&lt;/a&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">Knapper med ikoner (Lucide)</div>
			<div class="sg-example">
				<a href="#" class="b-button b-button--yellow"><i data-lucide="calendar" class="b-icon"></i> Kalender</a>
				<a href="#" class="b-button b-button--yellow"><i data-lucide="calendar-arrow-up" class="b-icon"></i> Bestill</a>
				<a href="#" class="b-button b-button--green"><i data-lucide="newspaper" class="b-icon"></i> Nyheter</a>
				<a href="#" class="b-button b-button--blue"><i data-lucide="map" class="b-icon"></i> Kart</a>
				<a href="#" class="b-button"><i data-lucide="download" class="b-icon"></i> Last ned</a>
			</div>
			<div class="sg-code"><code>&lt;a href="#" class="b-button b-button--yellow"&gt;
  &lt;i data-lucide="calendar" class="b-icon"&gt;&lt;/i&gt; Kalender
&lt;/a&gt;

&lt;!-- Vanlige ikoner: calendar, calendar-arrow-up, newspaper,
map, download, info, user, user-pen, log-out, facebook --&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">.b-icon (i tekst)</div>
			<div class="sg-example--white sg-example">
				<p>Tekst med <i data-lucide="info" class="b-icon"></i> ikon inline</p>
			</div>
			<div class="sg-code"><code>&lt;i data-lucide="info" class="b-icon"&gt;&lt;/i&gt;</code></div>
		</div>
	</section>

	<!-- BOKSER -->
	<section id="bokser" class="sg-section">
		<h1>Bokser</h1>

		<div class="sg-component">
			<div class="sg-label">.b-box--red</div>
			<div class="b-box b-box--red">
				<h2 style="margin-top:0;">Rød boks</h2>
				<p class="b-body-text">Innhold i en rød boks. Brukes typisk for viktige meldinger.</p>
			</div>
			<div class="sg-code"><code>&lt;div class="b-box b-box--red"&gt;
  &lt;h2&gt;Tittel&lt;/h2&gt;
  &lt;p&gt;Innhold...&lt;/p&gt;
&lt;/div&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">.b-box--yellow</div>
			<div class="b-box b-box--yellow">
				<h2 style="margin-top:0;">Gul boks</h2>
				<p class="b-body-text">Innhold i en gul boks. Brukes for hendelser og varsler.</p>
			</div>
			<div class="sg-code"><code>&lt;div class="b-box b-box--yellow"&gt;
  &lt;h2&gt;Tittel&lt;/h2&gt;
  &lt;p&gt;Innhold...&lt;/p&gt;
&lt;/div&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">.b-box--green</div>
			<div class="b-box b-box--green">
				<h2 style="margin-top:0;">Grønn boks</h2>
				<p class="b-body-text">Innhold i en grønn boks. Brukes for artikler og oppslag.</p>
			</div>
			<div class="sg-code"><code>&lt;div class="b-box b-box--green"&gt;
  &lt;h2&gt;Tittel&lt;/h2&gt;
  &lt;p&gt;Innhold...&lt;/p&gt;
&lt;/div&gt;</code></div>
		</div>
	</section>

	<!-- QUICKLINKS -->
	<section id="quicklinks" class="sg-section">
		<h1>Quicklinks</h1>

		<div class="sg-component">
			<div class="sg-label">.b-quicklinks (flex-basert knappekolleksjon)</div>
			<div class="sg-example">
				<div class="b-quicklinks">
					<a href="#" class="b-button b-button--yellow">Kalender</a>
					<a href="#" class="b-button b-button--yellow">Leie av fellesarealer</a>
					<a href="#" class="b-button b-button--yellow">Abonnement</a>
					<a href="#" class="b-button b-button--yellow">Kontakt</a>
				</div>
			</div>
			<div class="sg-code"><code>&lt;div class="b-quicklinks"&gt;
  &lt;a href="#" class="b-button b-button--yellow"&gt;Kalender&lt;/a&gt;
  &lt;a href="#" class="b-button b-button--yellow"&gt;Leie&lt;/a&gt;
&lt;/div&gt;</code></div>
		</div>
	</section>

	<!-- ARTIKLER -->
	<section id="artikler" class="sg-section">
		<h1>Artikler</h1>

		<div class="sg-component">
			<div class="sg-label">.b-article-plug (uten bilde)</div>
			<article class="b-article-plug b-box b-box--green">
				<a class="b-article-permalink" href="#"><?php echo date_i18n('j. F Y'); ?></a>
				<ul class="b-inline-list b-float-right">
					<li><a class="b-subject-link b-subject-link--small" href="#">Nyheter</a></li>
				</ul>
				<h1 class="b-article-heading--small">Eksempel på artikkelplug</h1>
				<div class="b-body-text">
					<p>Dette er et kort utdrag fra artikkelen som viser hvordan artikkelpluggen ser ut uten bilde.</p>
				</div>
			</article>
			<div class="sg-code"><code>&lt;article class="b-article-plug b-box b-box--green"&gt;
  &lt;a class="b-article-permalink" href="#"&gt;Dato&lt;/a&gt;
  &lt;ul class="b-inline-list b-float-right"&gt;
    &lt;li&gt;&lt;a class="b-subject-link b-subject-link--small"&gt;Kategori&lt;/a&gt;&lt;/li&gt;
  &lt;/ul&gt;
  &lt;h1 class="b-article-heading--small"&gt;Tittel&lt;/h1&gt;
  &lt;div class="b-body-text"&gt;&lt;p&gt;Utdrag...&lt;/p&gt;&lt;/div&gt;
&lt;/article&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">.b-article-plug--has-image (med bilde)</div>
			<article class="b-article-plug b-article-plug--has-image b-box b-box--green">
				<a class="b-article-plug__thumbnail" href="#">
					<img src="https://placehold.co/200x200/518347/white?text=Bilde" alt="Eksempelbilde">
				</a>
				<a class="b-article-permalink" href="#"><?php echo date_i18n('j. F Y'); ?></a>
				<ul class="b-inline-list b-float-right">
					<li><a class="b-subject-link b-subject-link--small" href="#">Praktisk</a></li>
				</ul>
				<h1 class="b-article-heading--small">Artikkel med bilde</h1>
				<div class="b-body-text">
					<p>Dette er et eksempel på en artikkelplug med bilde til venstre.</p>
				</div>
			</article>
			<div class="sg-code"><code>&lt;article class="b-article-plug b-article-plug--has-image b-box b-box--green"&gt;
  &lt;a class="b-article-plug__thumbnail" href="#"&gt;
    &lt;img src="bilde.jpg" alt=""&gt;
  &lt;/a&gt;
  ...
&lt;/article&gt;</code></div>
		</div>
	</section>

	<!-- HENDELSER -->
	<section id="hendelser" class="sg-section">
		<h1>Hendelser / Event-liste</h1>

		<div class="sg-component">
			<div class="sg-label">.b-event-list med tidslinje</div>
			<div class="b-event-list">
				<h2>Viktige datoer</h2>
				<ul class="b-event-list__timeline">
					<li class="b-event-list__item b-box b-box--yellow">
						<a href="#">
							<div class="b-article-permalink">15. desember <?php echo date('Y'); ?></div>
							<div class="b-event-list__title">Juleavslutning</div>
						</a>
					</li>
					<li class="b-event-list__item b-box b-box--yellow">
						<a href="#">
							<div class="b-article-permalink">31. desember <?php echo date('Y'); ?></div>
							<div class="b-event-list__title">Nyttårsfeiring</div>
						</a>
					</li>
					<li class="b-event-list__item b-box b-box--yellow">
						<a href="#">
							<div class="b-article-permalink">1. januar <?php echo date('Y') + 1; ?></div>
							<div class="b-event-list__title">Nyttårsmarsj</div>
						</a>
					</li>
				</ul>
				<a href="#" class="b-button b-button--yellow">Vis kalender</a>
			</div>
			<div class="sg-code"><code>&lt;div class="b-event-list"&gt;
  &lt;h2&gt;Viktige datoer&lt;/h2&gt;
  &lt;ul class="b-event-list__timeline"&gt;
    &lt;li class="b-event-list__item b-box b-box--yellow"&gt;
      &lt;a href="#"&gt;
        &lt;div class="b-article-permalink"&gt;Dato&lt;/div&gt;
        &lt;div class="b-event-list__title"&gt;Tittel&lt;/div&gt;
      &lt;/a&gt;
    &lt;/li&gt;
  &lt;/ul&gt;
&lt;/div&gt;</code></div>
		</div>
	</section>

	<!-- AUTHOR / HYTTEEIER -->
	<section id="author" class="sg-section">
		<h1>Hytteeier / Author</h1>

		<div class="sg-component">
			<div class="sg-label">.b-author-header (header med avatar)</div>
			<div class="sg-example--white sg-example">
				<div class="b-author-header">
					<div class="b-author-avatar">
						<?php echo get_avatar(get_current_user_id(), 120); ?>
					</div>
					<div class="b-author-info">
						<h1 style="margin:0;">Hytte 42</h1>
					</div>
				</div>
			</div>
			<div class="sg-code"><code>&lt;div class="b-author-header"&gt;
  &lt;div class="b-author-avatar"&gt;
    &lt;?php echo get_avatar($user_id, 120); ?&gt;
  &lt;/div&gt;
  &lt;div class="b-author-info"&gt;
    &lt;h1&gt;Hytte 42&lt;/h1&gt;
  &lt;/div&gt;
&lt;/div&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">.b-author-details med kontaktliste</div>
			<div class="b-author-details b-box b-box--green">
				<h2 style="margin-top:0;">Kontaktinformasjon</h2>
				<dl class="b-author-contact-list">
					<dt>Navn</dt>
					<dd>Ola Nordmann</dd>
					<dt>E-post</dt>
					<dd><a href="mailto:ola@example.com">ola@example.com</a></dd>
					<dt>Telefon</dt>
					<dd><a href="tel:+4712345678">+47 123 45 678</a></dd>
					<dt>På kartet</dt>
					<dd><a href="#">Hytte 42</a></dd>
				</dl>

				<h3>Alternativ kontakt</h3>
				<dl class="b-author-contact-list">
					<dt>Navn</dt>
					<dd>Kari Nordmann</dd>
					<dt>E-post</dt>
					<dd><a href="mailto:kari@example.com">kari@example.com</a></dd>
				</dl>
			</div>
			<div class="sg-code"><code>&lt;div class="b-author-details b-box b-box--green"&gt;
  &lt;h2&gt;Kontaktinformasjon&lt;/h2&gt;
  &lt;dl class="b-author-contact-list"&gt;
    &lt;dt&gt;Navn&lt;/dt&gt;
    &lt;dd&gt;Ola Nordmann&lt;/dd&gt;
    &lt;dt&gt;E-post&lt;/dt&gt;
    &lt;dd&gt;&lt;a href="mailto:..."&gt;...&lt;/a&gt;&lt;/dd&gt;
  &lt;/dl&gt;
  &lt;h3&gt;Alternativ kontakt&lt;/h3&gt;
  &lt;dl class="b-author-contact-list"&gt;...&lt;/dl&gt;
&lt;/div&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">.b-author-cabin-badge</div>
			<div class="sg-example">
				<span class="b-author-cabin-badge">Hytteeier</span>
			</div>
			<div class="sg-code"><code>&lt;span class="b-author-cabin-badge"&gt;Hytteeier&lt;/span&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">.b-author-bio</div>
			<div class="sg-example--white sg-example">
				<div class="b-author-bio b-body-text">
					<p>Dette er en kort biografi om hytteeieren. Her kan det stå litt om familien, hvor lenge de har hatt hytte på øya, eller andre interessante detaljer.</p>
				</div>
			</div>
			<div class="sg-code"><code>&lt;div class="b-author-bio b-body-text"&gt;
  &lt;p&gt;Biografitekst...&lt;/p&gt;
&lt;/div&gt;</code></div>
		</div>
	</section>

	<!-- EMNER -->
	<section id="emner" class="sg-section">
		<h1>Emner / Kategorier</h1>

		<div class="sg-component">
			<div class="sg-label">.b-subject-link</div>
			<div class="sg-example">
				<a href="#" class="b-subject-link">Standard emnelenke</a>
			</div>
			<div class="sg-code"><code>&lt;a href="#" class="b-subject-link"&gt;Emnenavn&lt;/a&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">.b-subject-link--small</div>
			<div class="sg-example">
				<a href="#" class="b-subject-link b-subject-link--small">Liten emnelenke</a>
			</div>
			<div class="sg-code"><code>&lt;a href="#" class="b-subject-link b-subject-link--small"&gt;Emnenavn&lt;/a&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">.b-subject-link--light (lyseblå, hover blir standard)</div>
			<div class="sg-example">
				<a href="#" class="b-subject-link b-subject-link--light">Lyseblå emnelenke</a>
				<a href="#" class="b-subject-link b-subject-link--small b-subject-link--light">Liten lyseblå</a>
			</div>
			<div class="sg-code"><code>&lt;a href="#" class="b-subject-link b-subject-link--light"&gt;Emnenavn&lt;/a&gt;
&lt;a href="#" class="b-subject-link b-subject-link--small b-subject-link--light"&gt;Liten&lt;/a&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">.b-inline-list med emnelenker</div>
			<div class="sg-example">
				<ul class="b-inline-list">
					<li><a href="#" class="b-subject-link">Praktisk informasjon</a></li>
					<li><a href="#" class="b-subject-link">Nyheter</a></li>
					<li><a href="#" class="b-subject-link">Arrangementer</a></li>
				</ul>
			</div>
			<div class="sg-code"><code>&lt;ul class="b-inline-list"&gt;
  &lt;li&gt;&lt;a href="#" class="b-subject-link"&gt;Emne&lt;/a&gt;&lt;/li&gt;
&lt;/ul&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">.b-subject-list (alfabetisk organisert)</div>
			<div class="sg-example--white sg-example">
				<ul class="b-subject-list clearfix">
					<li class="b-subject-list__item">
						<span class="b-subject-list__first-letter">A</span>
						<ul class="b-inline-list">
							<li><a href="#" class="b-subject-link">Arrangementer</a></li>
							<li><a href="#" class="b-subject-link">Avfall</a></li>
						</ul>
					</li>
					<li class="b-subject-list__item">
						<span class="b-subject-list__first-letter">B</span>
						<ul class="b-inline-list">
							<li><a href="#" class="b-subject-link">Båtplasser</a></li>
							<li><a href="#" class="b-subject-link">Brygger</a></li>
						</ul>
					</li>
				</ul>
			</div>
			<div class="sg-code"><code>&lt;ul class="b-subject-list clearfix"&gt;
  &lt;li class="b-subject-list__item"&gt;
    &lt;span class="b-subject-list__first-letter"&gt;A&lt;/span&gt;
    &lt;ul class="b-inline-list"&gt;
      &lt;li&gt;&lt;a href="#" class="b-subject-link"&gt;Emne&lt;/a&gt;&lt;/li&gt;
    &lt;/ul&gt;
  &lt;/li&gt;
&lt;/ul&gt;</code></div>
		</div>
	</section>

	<!-- SKJEMA -->
	<section id="skjema" class="sg-section">
		<h1>Skjemaelementer</h1>

		<div class="sg-component">
			<div class="sg-label">.b-form</div>
			<div class="sg-example--white sg-example">
				<form class="b-form" onsubmit="return false;">
					<p>
						<label>Navn</label>
						<input type="text" placeholder="Skriv inn navn">
					</p>
					<p>
						<label>E-post</label>
						<input type="email" placeholder="din@epost.no">
					</p>
					<p>
						<label>Velg kategori</label>
						<select>
							<option>Velg...</option>
							<option>Kategori 1</option>
							<option>Kategori 2</option>
						</select>
					</p>
					<p>
						<label>Melding</label>
						<textarea rows="4" placeholder="Skriv din melding her..."></textarea>
					</p>
					<p>
						<button type="submit" class="b-button b-button--green">Send inn</button>
					</p>
				</form>
			</div>
			<div class="sg-code"><code>&lt;form class="b-form"&gt;
  &lt;p&gt;
    &lt;label&gt;Navn&lt;/label&gt;
    &lt;input type="text"&gt;
  &lt;/p&gt;
  &lt;button class="b-button b-button--green"&gt;Send&lt;/button&gt;
&lt;/form&gt;</code></div>
		</div>
	</section>

	<!-- LISTER -->
	<section id="lister" class="sg-section">
		<h1>Lister</h1>

		<div class="sg-component">
			<div class="sg-label">.b-inline-list</div>
			<div class="sg-example">
				<ul class="b-inline-list">
					<li>Element 1</li>
					<li>Element 2</li>
					<li>Element 3</li>
				</ul>
			</div>
			<div class="sg-code"><code>&lt;ul class="b-inline-list"&gt;
  &lt;li&gt;Element 1&lt;/li&gt;
  &lt;li&gt;Element 2&lt;/li&gt;
&lt;/ul&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">Punktliste i .b-body-text</div>
			<div class="sg-example--white sg-example">
				<div class="b-body-text">
					<ul>
						<li>Første punkt</li>
						<li>Andre punkt</li>
						<li>Tredje punkt</li>
					</ul>
				</div>
			</div>
			<div class="sg-code"><code>&lt;div class="b-body-text"&gt;
  &lt;ul&gt;
    &lt;li&gt;Første punkt&lt;/li&gt;
  &lt;/ul&gt;
&lt;/div&gt;</code></div>
		</div>

		<div class="sg-component">
			<div class="sg-label">Nummerert liste i .b-body-text</div>
			<div class="sg-example--white sg-example">
				<div class="b-body-text">
					<ol>
						<li>Første steg</li>
						<li>Andre steg</li>
						<li>Tredje steg</li>
					</ol>
				</div>
			</div>
			<div class="sg-code"><code>&lt;div class="b-body-text"&gt;
  &lt;ol&gt;
    &lt;li&gt;Første steg&lt;/li&gt;
  &lt;/ol&gt;
&lt;/div&gt;</code></div>
		</div>
	</section>

</div>

<?php get_footer(); ?>
