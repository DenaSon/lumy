# Lumy Content Catalog

The Content Catalog is the first read interface over Lumy's normalized content and analytics data.

## Route

`/panel/content`

## Purpose

The catalog provides one place to inspect imported Instagram content together with the latest provider observation and Lumy's derived KPIs. It is intentionally read-only in this phase; manual Content Intelligence annotation will be added next.

## Data shown

Each content row can show:

- content type
- caption
- Instagram permalink
- platform post ID
- publication date
- latest views / reach / saves / shares
- high-intent rate
- retention rate
- provider skip rate
- analytics availability status

The catalog uses `Content::latestMetricSnapshot()` so factual counters and derived KPIs come from one consistent latest snapshot rather than mixing observations from different times.

## Search and filters

The page supports:

- search by caption, platform post ID, or permalink
- content type: Reel / Carousel / Feed
- analytics status: available / pending / unavailable / failed
- sorting by publication date or latest views / reach / saves / shares
- ascending or descending order
- pagination at 24 contents per page

Filter state is reflected in the URL so a catalog view can be revisited or shared.

## Metric semantics

Derived rates come from the Derived Metrics Layer and are formatted as percentages only in the UI. They remain decimal ratios in application code.

Provider `skip_rate` is already represented by Zernio as a percentage and is therefore displayed directly rather than multiplied by 100.

Missing factual inputs remain visibly missing (`—`) rather than being rendered as zero.

## Model helpers

`Content::coverMedia()` resolves the lowest-position media item for catalog thumbnails.

`Content::orderByLatestMetric($metric, $direction)` orders content by a supported metric from the latest provider observation. Supported catalog metrics are:

- views
- reach
- likes
- comments
- shares
- saves
- average watch time
- skip rate

The query uses provider observation time, falling back to capture time only when provider time is absent.

## Next phase

The next layer should add the Manual Annotation UI on top of the catalog so an operator can assign Pillar, Topic, Hook, Hook Type, Hook Source, Goal, CTA, and notes while preserving the catalog as the primary content navigation surface.
