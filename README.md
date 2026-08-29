# Yoast Redirect GraphQL Checker

A lightweight WordPress plugin that adds a custom GraphQL field to [WPGraphQL](https://www.wpgraphql.com/), allowing you to check if a given URL has a redirect configured in **Yoast SEO Premium**.

This is ideal for **headless WordPress** or **decoupled frontends** (e.g., Next.js, React, Gatsby) that need access to redirect logic stored in Yoast.

---

## ✨ Features

- 🔎 Check if a specific URL has a redirect configured in Yoast
- 🧠 Supports both **plain** and **regex** redirect formats
- ⚡ Efficient — does not return the full redirect list
- 🔁 Returns `origin`, `target`, `type`, and `format`
- 🧩 Designed for GraphQL-based frontends
- ✅ No database query — uses Yoast's internal redirect store

---

## 🧠 Use Case

You’re using Yoast SEO Premium to manage redirects in WordPress, and your frontend is built in React, Next.js, or another decoupled stack. This plugin allows your frontend to:

- ✅ Fetch redirect data at build/runtime
- ✅ Avoid relying on server-side redirect logic
- ✅ Match both exact and regex-based redirect rules

---

## 🛠 Installation

1. Clone or download this repo into your WordPress `plugins` folder:
wp-content/plugins/yoast-graphql-redirect-checker/


2. Activate the plugin from the **WordPress admin dashboard**.

---

## 🚀 GraphQL Usage

### 🔍 Query a redirect for a specific URL

```graphql
query {
  yoastRedirectForUrl(url: "/old-page") {
    origin
    target
    type
    format
  }
}
```

## 🔁 Example Response
```json
{
  "data": {
    "yoastRedirectForUrl": {
      "origin": "/old-page",
      "target": "/new-page",
      "type": "301",
      "format": "plain"
    }
  }
}
```

## ⚙️ Requirements
- WordPress 5.5+
- Yoast SEO Premium
- WPGraphQL

## 🧑‍💻 Developer Notes
- The plugin uses WPSEO_Redirect_Option()->get_from_option() to read the current list of configured redirects.
- Regex rules are evaluated using preg_match, and malformed patterns are safely ignored with error suppression.
- Matching is based only on the URL path (domain and query string are ignored).

## 📄 License
[MIT License](https://mit-license.org/)

Copyright (c) 2025 Finanzero

## 🤝 Contributing
Pull requests and suggestions are welcome! See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## 🙋‍♂️ Maintainers
Maintained by [Finanzero](https://github.com/finanzero). Originally created by [Leonardo Assef](https://github.com/assef).

## 🔗 Plugin Links
- [Yoast SEO Premium](yoast.com)
- [WPGraphQL](wpgraphql.com)