# AI Pre-review Checklist

Xác nhận AI pre-review đã kỹ trước khi request TL review:

- [ ] AI pre-review đã chạy trên phiên bản code cuối cùng
- [ ] Critical findings (nếu có) đã được fix
- [ ] Warnings (nếu có) đã được acknowledge — đã fix hoặc TL đã biết
- [ ] Code match implementation plan theo review
- [ ] Không có out-of-scope modifications bị flag
- [ ] Không có hardcoded values bị flag
- [ ] Error handling đã verify
- [ ] Business rules trong project-context/ được respect
- [ ] Đã perform security check
- [ ] Performance concerns đã được flag
- [ ] Regression risks đã được list
- [ ] Tests đã suggest hoặc đã viết
- [ ] Pre-review recommendation là PASS hoặc PASS WITH WARNINGS (không phải NEEDS FIX)
