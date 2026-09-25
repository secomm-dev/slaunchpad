import json,sys
d=json.load(open(sys.argv[1])); o=json.load(open(sys.argv[2])) if len(sys.argv)>2 else d
keys=['fs','fw','lh','c','bg','bd','bb','r','p','h','w','sh','td']
for view in ['login','create','forgot']:
  for vp in ['1280','375']:
    h=d.get(f'home-{view}-{vp}'); c=o.get(f'checkout-{view}-{vp}')
    if not h or not c: continue
    print(f'==== {view} {vp}  home.view={h["view"]} checkout.view={c["view"]}')
    for el in ['title','h2','blockTitle','blockTitleBox','label','labelSpan','input','primary','socialTitle','social','close']:
      a,b=h.get(el),c.get(el)
      if not a or not b: print('  ',el,'home' if a else '-', 'co' if b else '-'); continue
      diff={k:(a[k],b[k]) for k in keys if a[k]!=b[k]}
      print('  ',el, diff if diff else 'OK')
    for i,(a,b) in enumerate(zip(h['links'],c['links'])):
      diff={k:(a[k],b[k]) for k in keys+['t'] if a[k]!=b[k]}
      print('   link',i, diff if diff else 'OK')
    # offsets relative to card
    for el in ['h2','primary']:
      pass
