#
# Performance indexes for temporal cache management.
#
# The extension repeatedly queries pages/tt_content for the next upcoming
# starttime/endtime transition (see TemporalContentRepository). Without an
# index on these columns those become full table scans on large sites.
#
# The leading column is starttime/endtime respectively so that the
# "WHERE starttime > :now" / "WHERE endtime > :now" range scans and the
# MIN() aggregation can use the index. This also satisfies the index check
# performed by the "temporalcache:verify" CLI command.
#
# TYPO3's schema migrator merges these definitions into the core tables by
# KEY name; it never drops core columns/indexes.
#
CREATE TABLE pages (
	KEY idx_temporalcache_starttime (starttime, sys_language_uid),
	KEY idx_temporalcache_endtime (endtime, sys_language_uid)
);

CREATE TABLE tt_content (
	KEY idx_temporalcache_starttime (starttime, sys_language_uid),
	KEY idx_temporalcache_endtime (endtime, sys_language_uid)
);
