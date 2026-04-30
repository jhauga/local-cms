(function () {
  'use strict';

  var markedLibraryPromise = null;

  function initializeMarkdownConverter() {
    var contentElement;
    var renderJobs;

    if (document.body.classList.contains('localcms-theme')) {
      renderJobs = Array.prototype.map.call(document.querySelectorAll('[data-markdown-body]'), function (el) {
        return renderMarkdownIntoElement(el, el.textContent || '', {
          renderer: el.getAttribute('data-markdown-renderer') || 'default'
        });
      });

      Promise.all(renderJobs).catch(function (error) {
        if (window.console && typeof window.console.warn === 'function') {
          console.warn('One or more markdown bodies could not be rendered.', error);
        }
      });

      return;
    }

    contentElement = document.getElementById('content');

    if (!contentElement) {
      return;
    }

    fetch('file.md')
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Failed to load markdown file');
        }

        return response.text();
      })
      .then(function (markdown) {
        return renderMarkdownIntoElement(contentElement, markdown, {
          renderer: contentElement.getAttribute('data-markdown-renderer') || 'default'
        });
      })
      .catch(function (error) {
        contentElement.innerHTML = '<p style="color: #c43f26;">Error: ' + escapeHtml(error.message) + '</p>';
      });
  }

  function renderMarkdownIntoElement(element, markdown, options) {
    return Promise.resolve(toHtml(markdown, options))
      .then(function (html) {
        element.innerHTML = html;
        processMathElements(element);

        return html;
      })
      .catch(function (error) {
        var metadata = extractMarkdownMetadata(markdown);
        var fallbackHtml = convertMarkdownToHTML(removeMarkdownFrontmatter(markdown));

        if (metadata.examples) {
          fallbackHtml = processExampleLinks(fallbackHtml, metadata.examples);
        }

        element.innerHTML = fallbackHtml;
        processMathElements(element);

        if (window.console && typeof window.console.warn === 'function') {
          console.warn('Markdown rendering fell back to the built-in converter.', error);
        }

        return fallbackHtml;
      });
  }

  function processMathElements(container) {
    var root = container || document;
    var mathElements = root.querySelectorAll('math-renderer[data-latex]');

    mathElements.forEach(function (element) {
      var latex = element.getAttribute('data-latex');

      if (!latex) {
        return;
      }

      if (element.classList.contains('js-display-math')) {
        element.innerHTML = '\\[' + latex + '\\]';
      } else {
        element.innerHTML = '\\(' + latex + '\\)';
      }

      element.removeAttribute('data-latex');
    });

    if (window.MathJax && typeof window.MathJax.typesetPromise === 'function') {
      window.MathJax.typesetPromise(container ? [container] : []);
    }
  }

  function normalizeRenderOptions(options) {
    return {
      renderer: options && options.renderer === 'marked' ? 'marked' : 'default'
    };
  }

  function getMarkdownFrontmatterBounds(markdownText) {
    var lines;
    var startIndex = 0;
    var endIndex = -1;
    var index;

    if (typeof markdownText !== 'string') {
      return null;
    }

    lines = markdownText.split(/\r\n|\n|\r/);

    while (startIndex < lines.length && lines[startIndex].trim() === '') {
      startIndex += 1;
    }

    if (startIndex >= lines.length || lines[startIndex].trim() !== '---') {
      return null;
    }

    for (index = startIndex + 1; index < lines.length; index += 1) {
      if (lines[index].trim() === '---') {
        endIndex = index;
        break;
      }
    }

    if (endIndex === -1) {
      return null;
    }

    return {
      lines: lines,
      startIndex: startIndex,
      endIndex: endIndex
    };
  }

  function extractMarkdownMetadata(markdownText) {
    var metadata = {};
    var bounds;
    var lines;
    var index;
    var match;

    bounds = getMarkdownFrontmatterBounds(markdownText);

    if (!bounds) {
      return metadata;
    }

    lines = bounds.lines;

    for (index = bounds.startIndex + 1; index < bounds.endIndex; index += 1) {
      match = lines[index].trim().match(/^([A-Za-z0-9_-]+):\s*(.+)$/);

      if (!match) {
        continue;
      }

      metadata[match[1].toLowerCase()] = match[2].trim();
    }

    return metadata;
  }

  function removeMarkdownFrontmatter(markdownText) {
    var bounds;

    if (typeof markdownText !== 'string') {
      return typeof markdownText === 'string' ? markdownText : '';
    }

    bounds = getMarkdownFrontmatterBounds(markdownText);

    if (!bounds) {
      return markdownText;
    }

    return bounds.lines.slice(bounds.endIndex + 1).join('\n');
  }

  function processExampleLinks(html, exampleUrl) {
    var baseUrl;

    if (!exampleUrl) {
      return html;
    }

    baseUrl = exampleUrl.replace(/\/$/, '');

    return html.replace(/<a\s+href=["']([^"']+)["']([^>]*)>([\s\S]*?)<\/a>/gim, function (match, href, attrs, text) {
      if (href.indexOf('/') !== -1 && href.indexOf('http') !== 0 && href.indexOf('#') !== 0 && href.indexOf('/') !== 0) {
        return '<a href="' + baseUrl + '/blob/main/' + href + '"' + attrs + ' target="_blank" rel="external noopener">' + text + '</a>';
      }

      return match;
    });
  }

  function createCodeBlockHtml(language, code) {
    var escapedCode = escapeHtml(code);
    var languageClass = language ? ' class="language-' + language + '"' : '';

    return '<pre><code' + languageClass + '>' + escapedCode + '</code></pre>';
  }

  function protectLocalCmsCodeFences(markdown, replacer) {
    return markdown.replace(/^(`{3,})local-cms[^\n]*\n([\s\S]*?)\n\1/gm, function (match, fence, code) {
      return replacer(code);
    });
  }

  function getConfiguredMarkdownTemplates() {
    var source = window.LocalCmsMarkdownTemplates;
    var templates = {};

    if (!source) {
      return templates;
    }

    if (Array.isArray(source)) {
      source.forEach(function (template) {
        if (!template || typeof template.name !== 'string' || typeof template.markup !== 'string') {
          return;
        }

        templates[template.name.trim()] = template.markup;
      });

      return templates;
    }

    Object.keys(source).forEach(function (templateName) {
      if (typeof source[templateName] !== 'string') {
        return;
      }

      templates[templateName.trim()] = source[templateName];
    });

    return templates;
  }

  function htmlMarkdownWrapper(templateName) {
    var templates = getConfiguredMarkdownTemplates();

    return typeof templates[templateName] === 'string' ? templates[templateName] : null;
  }

  function applyMarkdownTemplates(markdown) {
    var lines = markdown.split('\n');
    var result = [];
    var index = 0;
    var line;
    var templateMatch;
    var templateName;
    var template;
    var markdownLines;
    var placeholderCount;
    var processedTemplate;
    var placeholderIndex;

    while (index < lines.length) {
      line = lines[index].trim();
      templateMatch = line.match(/^<!--\s*html:template=([A-Za-z0-9_-]+)\s*-->$/);

      if (!templateMatch) {
        result.push(lines[index]);
        index += 1;
        continue;
      }

      templateName = templateMatch[1];
      template = htmlMarkdownWrapper(templateName);

      if (!template) {
        result.push(lines[index]);
        index += 1;
        continue;
      }

      markdownLines = [];
      placeholderCount = (template.match(/\{__markdown__\}/g) || []).length;
      index += 1;

      while (index < lines.length && lines[index].trim() !== '') {
        markdownLines.push(lines[index]);
        index += 1;
      }

      processedTemplate = template;

      if (placeholderCount <= 1) {
        processedTemplate = processedTemplate.replace(/\{__markdown__\}/g, markdownLines.join('\n'));
      } else {
        for (placeholderIndex = 0; placeholderIndex < placeholderCount; placeholderIndex += 1) {
          processedTemplate = processedTemplate.replace('{__markdown__}', markdownLines[placeholderIndex] || '');
        }
      }

      processedTemplate = processedTemplate.replace(/\{__markdown__\}/g, '');
      result.push(processedTemplate);

      if (placeholderCount > 1 && markdownLines.length > placeholderCount) {
        result = result.concat(markdownLines.slice(placeholderCount));
      }

      if (index < lines.length && lines[index].trim() === '') {
        result.push(lines[index]);
        index += 1;
      }
    }

    return result.join('\n');
  }

  function parseNestedElements(commentLine) {
    var commentPattern = /<!--\s*(.*?)\s*-->/g;
    var matches = [];
    var match;
    var textContent;
    var elements;
    var html = '';
    var closingTags = [];

    while ((match = commentPattern.exec(commentLine)) !== null) {
      matches.push(match[1].trim());
    }

    if (matches.length === 0) {
      return null;
    }

    textContent = matches[matches.length - 1];
    elements = matches.slice(0, -1);

    if (matches.length === 1 && matches[0].indexOf('.') !== -1) {
      elements = [matches[0]];
      textContent = '';
    }

    if (elements.length === 0) {
      return null;
    }

    elements.forEach(function (element) {
      var elementMatch = element.match(/^(\w+)(?:\.(\S+))?$/);
      var tagName;
      var className;

      if (!elementMatch) {
        return;
      }

      tagName = elementMatch[1];
      className = elementMatch[2] || '';

      html += className !== ''
        ? '  <' + tagName + ' class="' + className + '">'
        : '  <' + tagName + '>';
      closingTags.unshift(tagName);
    });

    html += textContent;

    closingTags.forEach(function (tagName) {
      html += '</' + tagName + '>';
    });

    return html;
  }

  function parseHTMLWrapperBlocks(markdown) {
    var lines = markdown.split('\n');
    var result = [];
    var index = 0;
    var line;
    var containerLine;
    var containerMatch;
    var containerTag;
    var containerClass;
    var markdownContent;
    var siblingElements;
    var currentLine;
    var nestedHTML;
    var html;

    while (index < lines.length) {
      line = lines[index].trim();

      if (line !== '<!-- html:begin -->') {
        result.push(lines[index]);
        index += 1;
        continue;
      }

      index += 1;
      containerLine = lines[index] ? lines[index].trim() : '';
      containerMatch = containerLine.match(/^<!--\s*(\w+)\.(\S+)\s*-->$/);

      if (!containerMatch) {
        result.push('<!-- html:begin -->');
        continue;
      }

      containerTag = containerMatch[1];
      containerClass = containerMatch[2];
      markdownContent = [];
      siblingElements = [];
      index += 1;

      while (index < lines.length && lines[index].trim() !== '<!-- html:end -->') {
        currentLine = lines[index].trim();

        if (currentLine.indexOf('<!--') === 0 && currentLine.indexOf('-->') !== -1) {
          nestedHTML = parseNestedElements(currentLine);

          if (nestedHTML) {
            siblingElements.push(nestedHTML);
            index += 1;
            continue;
          }
        }

        markdownContent.push(lines[index]);
        index += 1;
      }

      html = '<' + containerTag + ' class="' + containerClass + '">';

      if (markdownContent.length > 0) {
        html += '\n' + markdownContent.join('\n');
      }

      if (siblingElements.length > 0) {
        html += '\n' + siblingElements.join('\n');
      }

      html += '\n</' + containerTag + '>';
      result.push(html);

      if (index < lines.length && lines[index].trim() === '<!-- html:end -->') {
        index += 1;
      }
    }

    return result.join('\n');
  }

  function loadMarkedLibrary() {
    var existingScript;

    if (window.marked && typeof window.marked.parse === 'function') {
      return Promise.resolve(window.marked);
    }

    if (markedLibraryPromise) {
      return markedLibraryPromise;
    }

    existingScript = document.getElementById('localcms-marked-loader');

    markedLibraryPromise = new Promise(function (resolve, reject) {
      if (window.marked && typeof window.marked.parse === 'function') {
        resolve(window.marked);
        return;
      }

      if (existingScript) {
        existingScript.addEventListener('load', function () {
          if (window.marked && typeof window.marked.parse === 'function') {
            resolve(window.marked);
            return;
          }

          reject(new Error('marked.js loaded without exposing window.marked.'));
        }, { once: true });
        existingScript.addEventListener('error', function () {
          reject(new Error('Failed to load marked.js.'));
        }, { once: true });
        return;
      }

      existingScript = document.createElement('script');
      existingScript.id = 'localcms-marked-loader';
      existingScript.async = true;
      existingScript.src = 'https://cdn.jsdelivr.net/npm/marked/lib/marked.umd.js';
      existingScript.addEventListener('load', function () {
        if (window.marked && typeof window.marked.parse === 'function') {
          resolve(window.marked);
          return;
        }

        reject(new Error('marked.js loaded without exposing window.marked.'));
      }, { once: true });
      existingScript.addEventListener('error', function () {
        reject(new Error('Failed to load marked.js.'));
      }, { once: true });
      document.head.appendChild(existingScript);
    });

    return markedLibraryPromise;
  }

  function resolveMarkedApi(markedApi) {
    if (markedApi && typeof markedApi.parse === 'function') {
      return markedApi;
    }

    if (markedApi && markedApi.marked && typeof markedApi.marked.parse === 'function') {
      return markedApi.marked;
    }

    return markedApi;
  }

  function sanitizeMarkedHtml(html) {
    var template = document.createElement('template');
    var blockedTags = ['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'form', 'input', 'button', 'textarea', 'select'];

    template.innerHTML = String(html);

    blockedTags.forEach(function (tagName) {
      Array.prototype.forEach.call(template.content.querySelectorAll(tagName), function (node) {
        node.remove();
      });
    });

    Array.prototype.forEach.call(template.content.querySelectorAll('*'), function (node) {
      Array.prototype.slice.call(node.attributes).forEach(function (attribute) {
        var attributeName = attribute.name.toLowerCase();

        if (attributeName.indexOf('on') === 0) {
          node.removeAttribute(attribute.name);
          return;
        }

        if ((attributeName === 'href' || attributeName === 'src' || attributeName === 'xlink:href') && /^\s*javascript:/i.test(attribute.value)) {
          node.removeAttribute(attribute.name);
        }
      });
    });

    return template.innerHTML;
  }

  function convertMarkdownWithMarked(markdown) {
    return loadMarkedLibrary().then(function (markedApi) {
      var parser = resolveMarkedApi(markedApi);
      var html;

      if (parser && typeof parser.parse === 'function') {
        html = parser.parse(markdown, {
          breaks: true,
          gfm: true
        });
      } else if (typeof parser === 'function') {
        html = parser(markdown);
      } else {
        throw new Error('marked.js API is unavailable.');
      }

      return sanitizeMarkedHtml(html);
    });
  }

  function toHtml(markdown, options) {
    var source = typeof markdown === 'string' ? markdown : String(markdown || '');
    var renderOptions = normalizeRenderOptions(options);
    var metadata = extractMarkdownMetadata(source);
    var content = removeMarkdownFrontmatter(source);
    var html;

    if (renderOptions.renderer === 'marked') {
      content = protectLocalCmsCodeFences(content, function (code) {
        return '\n' + createCodeBlockHtml('local-cms', code) + '\n';
      });

      content = parseHTMLWrapperBlocks(applyMarkdownTemplates(content));

      return convertMarkdownWithMarked(content).then(function (markedHtml) {
        if (metadata.examples) {
          return processExampleLinks(markedHtml, metadata.examples);
        }

        return markedHtml;
      });
    }

    html = convertMarkdownToHTML(content);

    if (metadata.examples) {
      html = processExampleLinks(html, metadata.examples);
    }

    return html;
  }

  function convertMarkdownToHTML(markdown) {
    var codeBlocks = [];
    var inlineCodes = [];
    var mathBlocks = [];
    var escapedChars = [];
    var footnoteData;
    var pendingParentAttributes = {};
    var lines;
    var processedLines;
    var processed = protectLocalCmsCodeFences(markdown, function (code) {
      var placeholder = '%%%CODEBLOCK' + codeBlocks.length + '%%%';

      codeBlocks.push(createCodeBlockHtml('local-cms', code));

      return placeholder;
    });

    processed = parseHTMLWrapperBlocks(applyMarkdownTemplates(processed));

    processed = processed.replace(/^(`{3,})math\n([\s\S]*?)\n\1/gm, function (match, fence, math) {
      var placeholder = '%%%MATHBLOCK' + mathBlocks.length + '%%%';
      var escaped = math.trim().replace(/"/g, '&quot;');

      mathBlocks.push('<math-renderer class="js-display-math" data-latex="' + escaped + '"></math-renderer>');

      return placeholder;
    });

    processed = processed.replace(/^(`{3,})(\w*)\n([\s\S]*?)\n\1/gm, function (match, fence, language, code) {
      var placeholder = '%%%CODEBLOCK' + codeBlocks.length + '%%%';

      codeBlocks.push(createCodeBlockHtml(language, code));

      return placeholder;
    });

    processed = processed.replace(/`([^`\n]+)`/g, function (match, code) {
      var placeholder = '%%%INLINECODE' + inlineCodes.length + '%%%';

      inlineCodes.push('<code>' + escapeHtml(code) + '</code>');

      return placeholder;
    });

    processed = processed.replace(/\\([*_`\[\]\\#>!~|])/g, function (match, character) {
      var placeholder = '%%%ESCAPED' + escapedChars.length + '%%%';

      escapedChars.push(character);

      return placeholder;
    });

    footnoteData = extractFootnoteDefinitions(processed);
    processed = footnoteData.text;

    lines = processed.split('\n');
    processedLines = [];

    lines.forEach(function (line, index) {
      var commentMatch = line.match(/^<!--\s*([^>]+)\s*-->$/);
      var nextIndex;

      if (commentMatch) {
        nextIndex = index + 1;

        while (nextIndex < lines.length && lines[nextIndex].trim() === '') {
          nextIndex += 1;
        }

        if (nextIndex < lines.length) {
          pendingParentAttributes[nextIndex] = commentMatch[1].trim();
        }

        return;
      }

      processedLines.push({
        line: line,
        parentAttr: pendingParentAttributes[index] || null
      });
    });

    processed = processedLines.map(function (item) {
      if (item.parentAttr) {
        return '%%%PARENTATTRSTART%%%' + item.parentAttr + '%%%PARENTATTREND%%%' + item.line;
      }

      return item.line;
    }).join('\n');

    processed = convertBlockElements(processed);
  processed = convertInlineElements(processed, footnoteData);
  processed = appendFootnotes(processed, footnoteData);

    codeBlocks.forEach(function (block, index) {
      processed = processed.replace('%%%CODEBLOCK' + index + '%%%', block);
    });

    inlineCodes.forEach(function (code, index) {
      processed = processed.replace('%%%INLINECODE' + index + '%%%', code);
    });

    mathBlocks.forEach(function (block, index) {
      processed = processed.replace('%%%MATHBLOCK' + index + '%%%', block);
    });

    processed = applyParentAttributes(processed);

    escapedChars.forEach(function (character, index) {
      processed = processed.replace('%%%ESCAPED' + index + '%%%', character);
    });

    return processed;
  }

  function escapeHtml(text) {
    var div = document.createElement('div');

    div.textContent = text;

    return div.innerHTML;
  }

  function escapeAttributeValue(text) {
    return escapeHtml(String(text)).replace(/"/g, '&quot;');
  }

  function extractInlineAttributes(text) {
    var match = text.match(/<!--\s*([^>]+)\s*-->/);

    if (match) {
      return {
        attributes: match[1].trim(),
        text: text.replace(/<!--\s*[^>]+\s*-->/, '').trim()
      };
    }

    return { attributes: null, text: text };
  }

  function applyAttributes(html, attributes) {
    if (!attributes) {
      return html;
    }

    return html.replace(/^<(\w+)/, '<$1 ' + attributes);
  }

  function extractParentAttributePrefix(line) {
    var attrMatch = line.match(/^%%%PARENTATTRSTART%%%([^%]+)%%%PARENTATTREND%%%(.*)$/);

    if (!attrMatch) {
      return {
        attributes: null,
        line: line
      };
    }

    return {
      attributes: attrMatch[1],
      line: attrMatch[2]
    };
  }

  function mergeAttributeText(baseAttributes, extraAttributes) {
    var classPattern = /\bclass=("([^"]*)"|'([^']*)')/i;
    var primary = (baseAttributes || '').trim();
    var secondary = (extraAttributes || '').trim();
    var primaryClass = '';
    var secondaryClass = '';
    var merged;

    if (primary === '') {
      return secondary;
    }

    if (secondary === '') {
      return primary;
    }

    primary = primary.replace(classPattern, function (match, value, doubleQuoted, singleQuoted) {
      primaryClass = (doubleQuoted || singleQuoted || '').trim();
      return '';
    }).trim();

    secondary = secondary.replace(classPattern, function (match, value, doubleQuoted, singleQuoted) {
      secondaryClass = (doubleQuoted || singleQuoted || '').trim();
      return '';
    }).trim();

    merged = [primary, secondary].filter(Boolean).join(' ').trim();

    if (primaryClass !== '' || secondaryClass !== '') {
      merged = 'class="' + [primaryClass, secondaryClass].filter(Boolean).join(' ') + '"' + (merged !== '' ? ' ' + merged : '');
    }

    return merged;
  }

  function applyParentAttributes(html) {
    var regex = /%%%PARENTATTRSTART%%%([^%]+)%%%PARENTATTREND%%%/g;
    var result = html;
    var match;

    while ((match = regex.exec(html)) !== null) {
      var attributes = match[1];
      var placeholder = match[0];
      var placeholderIndex = result.indexOf(placeholder);
      var afterPlaceholder;
      var tagMatch;
      var updatedContent;

      if (placeholderIndex === -1) {
        continue;
      }

      afterPlaceholder = result.substring(placeholderIndex + placeholder.length);
      tagMatch = afterPlaceholder.match(/^\s*<(\w+)/);

      if (!tagMatch) {
        result = result.replace(placeholder, '');
        continue;
      }

      updatedContent = afterPlaceholder.replace(/^\s*<(\w+)/, function (fullMatch, tagName) {
        var leadingWhitespace = fullMatch.slice(0, fullMatch.indexOf('<'));

        return leadingWhitespace + '<' + tagName + ' ' + attributes;
      });

      result = result.substring(0, placeholderIndex) + updatedContent;
    }

    return result;
  }

  function parseMarkdownTarget(rawTarget) {
    var value = rawTarget.trim();
    var destination = '';
    var remainder = '';
    var index = 0;
    var depth = 0;
    var character;
    var titleMatch;

    if (value === '') {
      return null;
    }

    if (value.charAt(0) === '<') {
      index = value.indexOf('>');

      if (index === -1) {
        return null;
      }

      destination = value.slice(1, index);
      remainder = value.slice(index + 1).trim();
    } else {
      while (index < value.length) {
        character = value.charAt(index);

        if (character === '\\' && index + 1 < value.length) {
          destination += value.charAt(index + 1);
          index += 2;
          continue;
        }

        if (/\s/.test(character) && depth === 0) {
          break;
        }

        if (character === '(') {
          depth += 1;
        } else if (character === ')' && depth > 0) {
          depth -= 1;
        }

        destination += character;
        index += 1;
      }

      remainder = value.slice(index).trim();
    }

    if (destination === '') {
      return null;
    }

    titleMatch = remainder.match(/^"([\s\S]*)"$/) ||
      remainder.match(/^'([\s\S]*)'$/) ||
      remainder.match(/^\(([\s\S]*)\)$/);

    return {
      destination: destination,
      title: titleMatch ? titleMatch[1] : ''
    };
  }

  function renderMarkdownImage(match, alt, source, attributes) {
    var target = parseMarkdownTarget(source);
    var attributeText = attributes ? ' ' + attributes.trim() : '';
    var title;

    if (!target) {
      return match;
    }

    title = target.title !== '' ? ' title="' + escapeAttributeValue(target.title) + '"' : '';

    return '<img src="' + escapeAttributeValue(target.destination) + '" alt="' + escapeAttributeValue(alt) + '"' + title + attributeText + '>';
  }

  function renderMarkdownLink(match, textValue, href, attributes) {
    var target = parseMarkdownTarget(href);
    var attributeText = attributes ? ' ' + attributes.trim() : '';
    var title;

    if (!target) {
      return match;
    }

    title = target.title !== '' ? ' title="' + escapeAttributeValue(target.title) + '"' : '';

    return '<a href="' + escapeAttributeValue(target.destination) + '"' + title + attributeText + '>' + textValue + '</a>';
  }

  function convertBlockElements(text) {
    var result = text;

    result = result.replace(/^(.+)\n={3,}$/gm, function (match, content) {
      return '<h1>' + content.trim() + '</h1>';
    });

    result = result.replace(/^(.+)\n-{3,}$/gm, function (match, content) {
      return '<h2>' + content.trim() + '</h2>';
    });

    result = result.replace(/^(#{1,6})\s+(.+?)(?:<!--\s*([^>]+)\s*-->)?$/gm, function (match, hashes, content, attributes) {
      var level = hashes.length;
      var attributeText = attributes ? ' ' + attributes.trim() : '';

      return '<h' + level + attributeText + '>' + content.trim() + '</h' + level + '>';
    });

    result = result.replace(/^([-*_]{3,})(?:<!--\s*([^>]+)\s*-->)?$/gm, function (match, rule, attributes) {
      var attributeText = attributes ? ' ' + attributes.trim() : '';

      return '<hr' + attributeText + '>';
    });

    result = convertBlockquotes(result);
    result = convertTables(result);
    result = convertLists(result);
    result = convertParagraphs(result);

    return result;
  }

  function convertBlockquotes(text) {
    var lines = text.split('\n');
    var result = [];
    var inBlockquote = false;
    var blockquoteLines = [];
    var alertType = null;
    var blockquoteAttributes = null;

    function detectAlertType(content) {
      var alertMatch = content.match(/^\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]$/i);
      var legacyAlertMatch;

      if (alertMatch) {
        return alertMatch[1].toLowerCase();
      }

      legacyAlertMatch = content.match(/^\*\*(Note|Warning|Tip|Important|Caution)\*\*$/i);

      if (legacyAlertMatch) {
        return legacyAlertMatch[1].toLowerCase();
      }

      return null;
    }

    function flushBlockquote() {
      if (!inBlockquote) {
        return;
      }

      result.push(renderBlockquote(blockquoteLines, alertType, blockquoteAttributes));
      blockquoteLines = [];
      inBlockquote = false;
      alertType = null;
      blockquoteAttributes = null;
    }

    lines.forEach(function (rawLine) {
      var lineData = extractParentAttributePrefix(rawLine);
      var line = lineData.line;
      var content;
      var detectedAlertType;

      if (line.indexOf('>') === 0) {
        if (!inBlockquote) {
          inBlockquote = true;
          blockquoteAttributes = lineData.attributes;
        }

        content = line.replace(/^>\s?/, '');
        detectedAlertType = detectAlertType(content);

        if (detectedAlertType && blockquoteLines.length === 0) {
          alertType = detectedAlertType;
          return;
        }

        if (lineData.attributes && !blockquoteAttributes) {
          blockquoteAttributes = lineData.attributes;
        }

        blockquoteLines.push(content);
        return;
      }

      if (inBlockquote) {
        flushBlockquote();
      }

      result.push(rawLine);
    });

    flushBlockquote();

    return result.join('\n');
  }

  function renderBlockquote(lines, alertType, attributes) {
    var content = lines.join('\n');
    var baseAttributes = alertType ? 'class="markdown-alert markdown-alert-' + alertType + '"' : '';
    var attributeText = mergeAttributeText(baseAttributes, attributes);
    var renderedAttributes = attributeText ? ' ' + attributeText : '';

    if (alertType) {
      return '<blockquote' + renderedAttributes + '>\n<p><strong>' +
        alertType.charAt(0).toUpperCase() + alertType.slice(1) +
        '</strong></p>\n<p>' + content + '</p>\n</blockquote>';
    }

    return '<blockquote' + renderedAttributes + '>\n<p>' + content + '</p>\n</blockquote>';
  }

  function convertTables(text) {
    var lines = text.split('\n');
    var result = [];
    var inTable = false;
    var tableLines = [];
    var alignments = [];

    lines.forEach(function (line) {
      if (line.trim().indexOf('|') === 0 || (line.indexOf('|') !== -1 && /^\|?[\s\w\-:|`*_\[\]()]+\|/.test(line))) {
        if (!inTable) {
          inTable = true;
        }

        if (/^\|?\s*:?-+:?\s*\|/.test(line)) {
          alignments = line.split('|').filter(function (cell) {
            return cell.trim() !== '';
          }).map(function (cell) {
            var trimmed = cell.trim();

            if (trimmed.indexOf(':') === 0 && trimmed.lastIndexOf(':') === trimmed.length - 1) {
              return 'center';
            }

            if (trimmed.lastIndexOf(':') === trimmed.length - 1) {
              return 'right';
            }

            return 'left';
          });
        } else {
          tableLines.push(line);
        }

        return;
      }

      if (inTable) {
        result.push(renderTable(tableLines, alignments));
        tableLines = [];
        alignments = [];
        inTable = false;
      }

      result.push(line);
    });

    if (inTable) {
      result.push(renderTable(tableLines, alignments));
    }

    return result.join('\n');
  }

  function renderTable(rows, alignments) {
    var headerCells;
    var bodyRows;
    var html;

    if (rows.length === 0) {
      return '';
    }

    function parseRow(row) {
      return row.split('|').filter(function (cell, index, cells) {
        return (index > 0 && index < cells.length - 1) || row.indexOf('|') !== 0;
      }).map(function (cell) {
        return cell.trim();
      }).filter(function (cell) {
        return cell !== '';
      });
    }

    headerCells = parseRow(rows[0]);
    bodyRows = rows.slice(1);
    html = '<table>\n<thead>\n<tr>\n';

    headerCells.forEach(function (cell, index) {
      var align = alignments[index] ? ' align="' + alignments[index] + '"' : '';

      html += '<th' + align + '>' + cell + '</th>\n';
    });

    html += '</tr>\n</thead>\n<tbody>\n';

    bodyRows.forEach(function (row) {
      var cells = parseRow(row);

      html += '<tr>\n';

      cells.forEach(function (cell, index) {
        var align = alignments[index] ? ' align="' + alignments[index] + '"' : '';

        html += '<td' + align + '>' + cell + '</td>\n';
      });

      html += '</tr>\n';
    });

    html += '</tbody>\n</table>';

    return html;
  }

  function convertLists(text) {
    var lines = text.split('\n');
    var result = [];
    var listStack = [];
    var pendingAttr = null;

    lines.forEach(function (line) {
      var attrMatch = line.match(/^%%%PARENTATTRSTART%%%([^%]+)%%%PARENTATTREND%%%(.*)$/);
      var actualLine = line;
      var ulMatch;
      var olMatch;
      var match;
      var indent;
      var content;
      var listType;
      var taskMatch;

      if (attrMatch) {
        pendingAttr = attrMatch[1];
        actualLine = attrMatch[2];
      }

      ulMatch = actualLine.match(/^(\s*)([-*+])\s+(.*)$/);
      olMatch = actualLine.match(/^(\s*)(\d+)\.\s+(.*)$/);

      if (!ulMatch && !olMatch) {
        while (listStack.length > 0) {
          result.push('</' + listStack.pop().type + '>');
        }

        if (pendingAttr) {
          result.push('%%%PARENTATTRSTART%%%' + pendingAttr + '%%%PARENTATTREND%%%' + actualLine);
          pendingAttr = null;
        } else {
          result.push(line);
        }

        return;
      }

      match = ulMatch || olMatch;
      indent = match[1].length;
      content = match[3];
      listType = ulMatch ? 'ul' : 'ol';
      taskMatch = content.match(/^\[([ xX])\]\s*(.*)$/);

      while (listStack.length > 0 && listStack[listStack.length - 1].indent > indent) {
        result.push('</' + listStack.pop().type + '>');
      }

      if (listStack.length === 0 || listStack[listStack.length - 1].indent < indent || listStack[listStack.length - 1].type !== listType) {
        var listAttributes = pendingAttr ? ' ' + pendingAttr : '';

        pendingAttr = null;
        result.push('<' + listType + listAttributes + '>');
        listStack.push({ type: listType, indent: indent });
      }

      if (taskMatch) {
        var taskInlineData = extractInlineAttributes(taskMatch[2]);
        var checked = taskMatch[1].toLowerCase() === 'x' ? ' checked' : '';
        var checkboxAttributes = taskInlineData.attributes ? ' ' + taskInlineData.attributes : '';

        result.push('<li class="task-list-item"><input type="checkbox"' + checked + checkboxAttributes + '> ' + taskInlineData.text + '</li>');
        return;
      }

      (function () {
        var inlineData = extractInlineAttributes(content);
        var liAttributes = inlineData.attributes ? ' ' + inlineData.attributes : '';

        result.push('<li' + liAttributes + '>' + inlineData.text + '</li>');
      })();
    });

    while (listStack.length > 0) {
      result.push('</' + listStack.pop().type + '>');
    }

    return result.join('\n');
  }

  function convertParagraphs(text) {
    var lines = text.split('\n');
    var result = [];
    var paragraph = [];

    function isBlockElement(line) {
      return line.indexOf('<') === 0 ||
        line.indexOf('#') === 0 ||
        /^[-*_]{3,}/.test(line) ||
        line.indexOf('>') === 0 ||
        line.indexOf('|') === 0 ||
        /^\d+\.\s/.test(line) ||
        /^[-*+]\s/.test(line) ||
        /^%%%CODEBLOCK/.test(line) ||
        /^%%%MATHBLOCK/.test(line) ||
        /^%%%PARENTATTRSTART%%%/.test(line);
    }

    lines.forEach(function (line) {
      if (line.trim() === '') {
        if (paragraph.length > 0) {
          result.push('<p>' + paragraph.join(' ') + '</p>');
          paragraph = [];
        }

        result.push('');
        return;
      }

      if (isBlockElement(line)) {
        if (paragraph.length > 0) {
          result.push('<p>' + paragraph.join(' ') + '</p>');
          paragraph = [];
        }

        result.push(line);
        return;
      }

      if (line.lastIndexOf('\\') === line.length - 1 || /\s{2}$/.test(line)) {
        paragraph.push(line.replace(/\\$/, '').replace(/\s{2}$/, '') + '<br>');
        return;
      }

      paragraph.push(line);
    });

    if (paragraph.length > 0) {
      result.push('<p>' + paragraph.join(' ') + '</p>');
    }

    return result.join('\n');
  }

  function extractFootnoteDefinitions(text) {
    var lines = text.split('\n');
    var result = [];
    var definitions = {};
    var index = 0;
    var match;
    var continuationMatch;
    var contentLines;

    while (index < lines.length) {
      match = lines[index].match(/^\[\^([A-Za-z0-9_-]+)\]:\s*(.*)$/);

      if (!match) {
        result.push(lines[index]);
        index += 1;
        continue;
      }

      contentLines = [match[2].trim()];
      index += 1;

      while (index < lines.length) {
        continuationMatch = lines[index].match(/^(?: {2,}|\t)(.*)$/);

        if (!continuationMatch) {
          break;
        }

        contentLines.push(continuationMatch[1]);
        index += 1;
      }

      definitions[match[1]] = contentLines.join('\n').trim();
    }

    return {
      text: result.join('\n'),
      definitions: definitions,
      order: [],
      numbers: {}
    };
  }

  function sanitizeFootnoteId(label) {
    return String(label).toLowerCase().replace(/[^a-z0-9_-]+/g, '-');
  }

  function renderInlineMarkup(text) {
    var result = text;

    result = result.replace(/!\[([^\]]*)\]\(((?:[^()]|\([^()]*\))+?)\)(?:<!--\s*([^>]+)\s*-->)?/g, renderMarkdownImage);

    result = result.replace(/\[([^\]]+)\]\(((?:[^()]|\([^()]*\))+?)\)(?:<!--\s*([^>]+)\s*-->)?/g, renderMarkdownLink);

    result = result.replace(/\*\*\*(.+?)\*\*\*(?:<!--\s*([^>]+)\s*-->)?/g, function (match, content, attributes) {
      var attributeText = attributes ? ' ' + attributes.trim() : '';

      return '<strong' + attributeText + '><em>' + content + '</em></strong>';
    });

    result = result.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    result = result.replace(/__(.+?)__/g, '<strong>$1</strong>');
    result = result.replace(/\*(.+?)\*/g, '<em>$1</em>');
    result = result.replace(/_(.+?)_/g, '<em>$1</em>');
    result = result.replace(/~~(.+?)~~/g, '<del>$1</del>');

    result = result.replace(/\$`([^`]+)`\$/g, function (match, math) {
      return '<math-renderer class="js-inline-math" data-latex="' + math.replace(/"/g, '&quot;') + '"></math-renderer>';
    });

    result = result.replace(/\$([^$\n]+)\$/g, function (match, math) {
      if (/^\d+(\.\d{2})?$/.test(math)) {
        return match;
      }

      return '<math-renderer data-latex="' + math.replace(/"/g, '&quot;') + '"></math-renderer>';
    });

    result = result.replace(/\$\$\n?([\s\S]+?)\n?\$\$/g, function (match, math) {
      return '<math-renderer class="js-display-math" data-latex="' + math.trim().replace(/"/g, '&quot;') + '"></math-renderer>';
    });

    return result;
  }

  function unescapeMarkdown(text) {
    return text.replace(/\\([*_`#\[\]()\\~])/g, '$1');
  }

  function convertInlineElements(text, footnoteData) {
    var result = renderInlineMarkup(text);

    result = result.replace(/\[\^([A-Za-z0-9_-]+)\]/g, function (match, label) {
      var number;
      var footnoteId;

      if (!footnoteData || !Object.prototype.hasOwnProperty.call(footnoteData.definitions, label)) {
        return match;
      }

      if (!Object.prototype.hasOwnProperty.call(footnoteData.numbers, label)) {
        footnoteData.order.push(label);
        footnoteData.numbers[label] = footnoteData.order.length;
      }

      number = footnoteData.numbers[label];
      footnoteId = sanitizeFootnoteId(label);

      return '<sup id="fnref-' + footnoteId + '"><a href="#fn-' + footnoteId + '">' + number + '</a></sup>';
    });

    return unescapeMarkdown(result);
  }

  function appendFootnotes(html, footnoteData) {
    var items;

    if (!footnoteData || footnoteData.order.length === 0) {
      return html;
    }

    items = footnoteData.order.map(function (label) {
      var footnoteId = sanitizeFootnoteId(label);
      var number = footnoteData.numbers[label];
      var content = unescapeMarkdown(renderInlineMarkup(String(footnoteData.definitions[label] || '').replace(/\n+/g, ' ')));

      return '<li id="fn-' + footnoteId + '">' + content + ' <a class="footnote-backref" href="#fnref-' + footnoteId + '" aria-label="Back to reference ' + number + '">↩</a></li>';
    }).join('\n');

    return html + '\n<section class="footnotes">\n<ol>\n' + items + '\n</ol>\n</section>';
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeMarkdownConverter, { once: true });
  } else {
    initializeMarkdownConverter();
  }

  window.MarkdownConverter = { toHtml: toHtml };
})();
