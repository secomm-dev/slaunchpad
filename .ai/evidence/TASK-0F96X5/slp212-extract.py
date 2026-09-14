#!/usr/bin/env python3
"""SLP-212: extract visible UI strings (text nodes + attrs + <option>) from saved PLP HTML."""
import sys, re, html
from html.parser import HTMLParser

class Extractor(HTMLParser):
    SKIP = {"script", "style", "noscript", "template"}
    ATTRS = {"aria-label", "title", "alt", "placeholder"}
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.skip_depth = 0
        self._in_option = False
        self.strings = set()
    def handle_starttag(self, tag, attrs):
        if tag in self.SKIP:
            self.skip_depth += 1
        d = dict(attrs)
        for a in self.ATTRS:
            if d.get(a):
                self.strings.add("[attr:%s] %s" % (a, d[a].strip()))
        if tag == "option":
            self._in_option = True
    def handle_endtag(self, tag):
        if tag in self.SKIP and self.skip_depth > 0:
            self.skip_depth -= 1
        if tag == "option":
            self._in_option = False
    def handle_data(self, data):
        if self.skip_depth:
            return
        t = re.sub(r"\s+", " ", data).strip()
        if t:
            tag = "[opt] " if self._in_option else ""
            self.strings.add(tag + t)

_out = set()
for path in sys.argv[1:]:
    p = Extractor()
    p.feed(open(path, encoding="utf-8", errors="replace").read())
    _out |= p.strings

# decode any residual numeric entities from aria attrs (LL-0005)
def unesc(s):
    s = html.unescape(s)
    return re.sub(r"&#x?[0-9a-fA-F]+;", "", s)

for s in sorted(_out, key=str.lower):
    print(unesc(s))
