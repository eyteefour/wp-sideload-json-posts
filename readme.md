# WP Sideload JSON Posts

Just a proof of concept touching on various WordPress concepts and functionality. Some key features include:

* Registers output as a Block if Block Theme Support is active.
* Registers a shortode for legacy functionality.
* Scheduling and caching logic for improved performance. Ideally default WP cron is disabled and scheduled actions are handled using a system-level cron processor.
* Engine has two modes:
  * __Basic__: Simply captures and stores JSON data. Serves it up to the front-end using a custom REST endpoint.
  * __Post__: Captures and parses the JSON data. Stores entries as invidual posts using WordPress' core posts functionality in order to best leverage native styles, taxonomy, media, permalinks, archives, pagination, etc. as desired.

Since it's just a spec project please be aware that numerous assumptions have been made and much of the error checking and sanitization logic that would otherwise be considered simply isn't present. I've left comments throughout to remind myself what was going through my head when I fist wrote this.

This can be cleaner and leaner, but I only threw some limited time at it given the broad scope of the functionality. A few high-level concepts like WordPress back-end and WordPress front-end development, Semantic HTML, Javascript, Basic CSS, et.