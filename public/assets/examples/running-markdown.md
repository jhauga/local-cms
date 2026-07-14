---
type: mix
groups: {
 news: {
  tags: news, announcements
  keywords: bulletin, update, roundup
 },
 guides: {
  tags: guide, how-to
  keywords: tutorial, walkthrough, reference
 },
 global: {
  tags: post, blog
  keywords: post, blog, article
 }
}
default-type: post
default-title: ${h1[0].text}
default-description: ${p[0].text}
default-tags: ${groups.global}
default-keywords: ${groups.global}
default-status: publish
---

---

```metadata
title: Welcome to Acme Corp News
description: A short introduction to the Acme Corp announcement feed.
type: post
tags: ${groups.news}
keywords: ${groups.news}
```

# Welcome to Acme Corp News

Acme Corp publishes product announcements, release notes, and company
updates in this feed. Every item in this document was authored in a single
running markdown file and imported in one pass.

---

```metadata
title: Getting Started Guide
type: page
status: publish
tags: ${groups.guides}
keywords: ${groups.guides}
```

# Getting Started Guide

This page walks a new reader through the basics:

- Create an account with an address like `jane.doe@example.com`
- Confirm the account from the welcome message
- Sign in at `https://www.example.com`

Each step links to a longer reference article when more detail is needed.

---

```metadata
type: post
status: draft
```

# Quarterly Roundup

Data needed. Draft not started.

---

# Spring Release Notes

The spring release focuses on speed and stability. Page loads are faster,
search results are more accurate, and the editor autosaves drafts.

Highlights:

- Faster page loads across the site
- Improved search relevance
- Automatic draft saving in the editor

Full details land in the knowledge base at `https://docs.example.com`.
