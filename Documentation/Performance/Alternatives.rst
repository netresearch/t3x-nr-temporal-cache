.. include:: /Includes.rst.txt

.. _performance-alternatives:

======================
Alternative approaches
======================

This extension is not the only way to keep time-based content current, and for some sites
it is not the best one.
The approaches below solve the same problem outside the page cache lifetime.

None of them is provided by this extension; they are listed so the choice can be an
informed one.

.. _performance-alternatives-comparison:

The trade-off in one line each
==============================

.. list-table::
    :header-rows: 1
    :widths: 22 39 39

    * - Approach
      - What it costs
      - What it buys
    * - This extension
      - Cache hits: entries expire earlier, or are flushed by a task.
      - Correct output for everything on the page, with no template changes.
    * - Uncached menu (``USER_INT``)
      - CPU on every request, for the menu only. The page cannot be cached whole at the
        edge.
      - Exact menus, no cache churn for the rest of the page.
    * - SSI / ESI
      - Infrastructure complexity, and a server or CDN that supports it.
      - Exact fragments with the rest of the page fully cached, including at the edge.
    * - Client-side loading
      - A request after page load; the fragment is invisible to crawlers and needs an
        accessible fallback.
      - The page HTML stays fully cacheable and static.
    * - Scheduled cache clearing
      - Cache hits at fixed intervals, whether or not anything changed.
      - Almost no setup.
    * - Manual clearing
      - Editorial attention, and it will be forgotten.
      - Nothing to install.

.. _performance-alternatives-user-int:

Uncached menus (``USER_INT``)
=============================

Render the navigation outside the page cache so it is rebuilt on every request.

.. code-block:: typoscript
    :caption: TypoScript

    lib.mainMenu = USER_INT
    lib.mainMenu {
        userFunc = MyVendor\MyExtension\Menu\MenuProcessor->render
    }

Fits when the temporal content is only in menus.
The rest of the page keeps its normal cache lifetime and nothing has to expire early, which
is the opposite trade to this extension: a steady per-request cost instead of periodic
cache loss.

Does not help with temporal content in the page body, and a page containing a ``USER_INT``
cannot be delivered from a reverse proxy as a whole.

.. _performance-alternatives-esi:

SSI and ESI
===========

Keep the page cached and let the web server or CDN assemble an uncached fragment into it at
delivery time.

.. code-block:: html
    :caption: Template, ESI

    <div class="navigation">
        <esi:include src="/menu-fragment" />
    </div>

.. code-block:: text
    :caption: Varnish VCL

    sub vcl_recv {
        if (req.url ~ "^/menu-fragment") {
            return (pass);
        }
    }

.. code-block:: apache
    :caption: Apache, SSI

    <IfModule mod_include.c>
        Options +Includes
        AddOutputFilter INCLUDES .html
    </IfModule>

Fits a site that already runs Varnish or a CDN with ESI support.
It is the only approach here that keeps both the page fully cached at the edge and the
fragment exact.

The cost is operational: another moving part in the delivery chain, and debugging that
spans TYPO3 and the proxy.

.. _performance-alternatives-client-side:

Client-side loading
===================

Ship the page without the time-sensitive fragment and fetch it after load.

.. code-block:: javascript
    :caption: Frontend

    const response = await fetch('/api/menu');
    const items = await response.json();

    const list = document.querySelector('.navigation ul');
    list.replaceChildren(...items.map(item => {
        const link = document.createElement('a');
        link.href = item.url;
        link.textContent = item.title;

        const entry = document.createElement('li');
        entry.append(link);

        return entry;
    }));

Fits an application-style frontend that is already doing this for other data.

For navigation it is usually the wrong choice: search engines and assistive technology need
the links in the delivered HTML, and a fragment that appears after load is a layout shift.
If it is used, the server-rendered fallback has to be correct on its own.

.. _performance-alternatives-scheduled-clearing:

Scheduled cache clearing
========================

.. code-block:: bash
    :caption: Crontab

    0 * * * * /path/to/typo3/vendor/bin/typo3 cache:flush

Fits a site with a fixed editorial rhythm — everything publishes at 09:00, 12:00 and 17:00 —
where clearing shortly after those times is enough.

It does not solve the underlying problem: between two runs the output is stale, and every
run discards the whole cache whether or not a transition happened.
Compared to this extension with ``global`` scoping, it is the same full flush on a fixed
schedule instead of on an actual transition.

Combining harmonization with the extension gets a similar grouping effect without discarding
caches that nothing invalidated; see :ref:`performance-strategies-harmonization`.

.. _performance-alternatives-manual:

Manual clearing
===============

The editor clears the cache after the scheduled moment has passed.

This is the status quo the extension exists to replace.
It is listed for completeness: it works, it costs nothing to set up, and it fails the first
time somebody is on holiday.

.. _performance-alternatives-choosing:

Choosing between them
=====================

Where is the temporal content?
   Only in menus → an uncached menu or ESI keeps the page cache intact.
   In the page body → this extension, or client-side loading for that fragment.

What is in front of TYPO3?
   A CDN or Varnish with ESI support makes ESI the strongest option.
   Without one, ESI is not available and an uncached menu costs origin CPU on every request.

How exact does it have to be?
   Exact to the second → this extension with ``dynamic`` timing, an uncached menu, or ESI.
   Within a few minutes → this extension with ``scheduler`` timing, or scheduled clearing.

Are the approaches mutually exclusive?
   No.
   An uncached menu plus this extension for content elements is a reasonable split: the
   menu never goes stale, and the extension only has to watch ``tt_content``.

.. _performance-alternatives-next-steps:

Next steps
==========

- :ref:`decision-guide` — choosing a configuration for this extension
- :ref:`performance-strategies` — the settings that change its cost
- :ref:`performance-limitations` — what it cannot do
- :ref:`installation` — installing it
