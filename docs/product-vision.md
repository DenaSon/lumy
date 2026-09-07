# Lumy — Product Vision & Development Roadmap

## 1. Vision

Lumy is a **Content Intelligence System** for Lumixo.

Its purpose is not merely to display Instagram analytics. Lumy turns Lumixo's content history and audience behavior into a learning and decision-support system that helps answer:

> What should Lumixo publish next, and what evidence supports that decision?

Core loop:

`Content → Performance → Pattern → Insight → Decision → New Content → New Data`

## 2. Problem

Instagram exposes performance metrics such as views, reach, likes, comments, saves, shares, watch time, skip rate, demographics, and account growth.

Those metrics explain **what happened**, but not reliably **why it happened**.

Lumy combines factual platform data with structured content annotations such as:

- Content Pillar
- Topic / Subtopic
- Hook
- Hook Type
- Hook Source
- CTA
- Content Goal
- Format
- Duration
- Production Style

This creates a dataset that can reveal which content patterns consistently work for Lumixo.

## 3. Product Goals

Lumy should progressively answer:

### Performance
- Which contents perform best and worst?
- How does performance change with content age?
- Which contents produce strong reach, saves, shares, comments, or retention?

### Hook
- Which hook types reduce skip rate?
- Which hooks improve watch time and retention?
- Which hook patterns correlate with saves and shares?

### Topic
- Which pillars and topics create reach?
- Which topics create high-intent engagement?
- Which topics consistently underperform?

### Format
- Reel or Carousel?
- Which Reel durations retain attention better?
- Which formats fit awareness, education, or community goals?

### Growth
- Which content periods correlate with account growth?
- Are reach spikes followed by sustained growth?
- Which content clusters repeatedly support growth?

## 4. Data Layers

### 4.1 Factual Data

Imported from Instagram through the provider integration:

- Content metadata and caption
- Publish date and media type
- Views / Reach / Impressions
- Likes / Comments / Saves / Shares / Reposts
- Average Watch Time
- Total Watch Time
- Skip Rate
- Video Duration
- Account Insights
- Audience Demographics
- Follower Snapshots

This layer answers:

> What happened?

### 4.2 Content Annotation

Information that cannot be reliably obtained from the API is registered manually or later with AI assistance:

- Content Pillar
- Topic / Subtopic
- Content Goal
- Hook
- Hook Type
- Hook Source
- CTA
- Target Audience
- Production Style
- Cover Style
- Notes

This layer answers:

> What kind of content was this?

### 4.3 Derived Intelligence

Calculated metrics and learned patterns:

- Save Rate
- Share Rate
- Comment Rate
- Engagement Rate
- High Intent Rate
- Average Retention
- View Velocity
- Content-age normalization
- Hook performance
- Topic performance
- Format performance

This layer answers:

> Why did it perform this way?

## 5. Manual Hook Annotation

Manual Hook Annotation is a **V1 core feature**.

A hook may exist on the cover, as video overlay text, as a spoken opening, on the first Carousel slide, or in the caption. The API cannot reliably extract these elements.

Each content item must therefore support one or more manually registered hooks with:

- `text`
- `type`
- `source`
- `position`
- `is_primary`
- `notes`

Initial Hook Types:

- question
- curiosity
- problem
- how_to
- warning
- contrarian
- list
- result
- news
- story
- statement

Initial Hook Sources:

- cover
- video_overlay
- spoken
- carousel_first_slide
- caption
- other

The system should enable analysis such as:

- Hook Type → Skip Rate
- Hook → Retention
- Hook → Save Rate
- Hook → Share Rate

## 6. Content DNA

Each content item has a structured Content DNA composed of its Pillar, Topic, Hook, Format, Duration, Goal, CTA, Style, and Performance metrics.

Long-term objective:

> Discover Lumixo's winning content patterns.

## 7. Performance Principles

Views alone do not define success.

Performance should be evaluated across:

- **Reach:** Views, Reach, Impressions
- **Engagement:** Likes, Comments
- **High Intent:** Saves, Shares
- **Retention:** Average Watch Time, Retention, Skip Rate
- **Growth:** Follower Growth, Account Growth Trends

A future Content Score must consider the content's goal. Educational, awareness, and community content should not use identical scoring weights.

## 8. Data Integrity

### Snapshots
Analytics must not be overwritten. Metric snapshots should preserve performance over time, including windows such as 24h, 72h, 7d, 30d, 90d, and lifetime.

### NULL vs Zero
Unavailable metrics must remain `NULL`. Missing analytics must never be interpreted as zero performance.

### Provider Payloads
Important raw provider responses may be retained for debugging and auditability while the normalized database remains the application source of truth.

## 9. V1 Scope

### Data Ingestion
- Instagram account
- Historical contents
- Post and Reel analytics
- Account insights
- Demographics
- Follower snapshots

### Content Catalog
- Content listing
- Caption and media preview
- Publish date
- Current metrics
- Analytics history

### Manual Annotation
- Hooks
- Hook Type / Source
- Pillars
- Topics
- CTA
- Content Goal
- Notes

### Analytics
- Rankings
- Save / Share / Comment rates
- Retention and Skip Rate
- Hook analysis
- Topic analysis
- Format analysis
- Trends

### Dashboard
- Growth
- Content Performance
- Reels
- Audience
- Content Intelligence

## 10. Out of Scope for V1

- Social scheduling and automatic publishing
- Comment management
- CRM
- Ad management
- Video editing
- Competitor intelligence
- Social listening
- Viral prediction
- Autonomous content generation
- Complex ML models

V1 focus:

> Build a trustworthy Content Intelligence foundation.

## 11. AI Vision

AI is introduced only after reliable data, annotations, and statistical patterns exist.

`Data → Clean Data → Annotation → Statistics → Patterns → AI Assistance`

AI recommendations must be evidence-based and cite Lumixo's own historical performance.

## 12. Experimentation

A later phase should support hypotheses and controlled content experiments.

Example:

**Hypothesis:** Question hooks reduce Skip Rate.

Compare multiple Question-hook Reels against Statement-hook Reels using Skip Rate, Average Watch Time, and Retention.

Cycle:

`Build → Measure → Learn`

## 13. Development Roadmap

### Phase 1 — Foundation & Observatory
**Goal:** trustworthy source of truth.

- Database schema
- Provider integration
- Content synchronization
- Analytics snapshots
- Account snapshots
- Demographics
- Content catalog
- Basic dashboard

### Phase 2 — Annotation & Intelligence
**Goal:** understand why content performs.

- Hook management
- Pillars and topics
- CTA and content goals
- Batch annotation workflow
- Derived KPIs
- Hook / Topic / Format analysis
- Reel retention analysis

### Phase 3 — Decision Support
**Goal:** turn insights into better content decisions.

- Content DNA
- Comparative analysis
- Pattern detection
- Content scoring
- Trend detection
- Recommendations
- Experiment tracking

### Phase 4 — AI Content Strategist
**Goal:** use Lumixo's own evidence to guide future strategy.

- AI-assisted analysis
- Evidence-based recommendations
- Content opportunity detection
- Topic and Hook suggestions
- Experiment recommendations
- Strategy summaries

## 14. Technical Foundation

Lumy is based on the Coreflare/xDeploy foundation.

Primary stack:

- Laravel
- Livewire
- Blade
- MaryUI
- Tailwind CSS
- DaisyUI
- MySQL
- Laravel Queue / Scheduler
- Zernio API

Architecture:

`Provider APIs → Sync Layer → Local Database → Analytics Layer → Intelligence Layer → UI / AI`

The local database is Lumy's source of truth.

## 15. Product North Star

Lumy succeeds when it can make this question easy to answer:

> What should Lumixo publish next, and what evidence supports that decision?

The long-term asset is the accumulated combination of Lumixo content history, audience behavior, content taxonomy, performance history, experiments, learned patterns, and decision history.
