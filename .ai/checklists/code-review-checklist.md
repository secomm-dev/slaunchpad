# Code Review Checklist

## Architecture & Design

- [ ] Code theo implementation plan
- [ ] Architecture impact consistent với design của project
- [ ] Design patterns consistent với phần còn lại của codebase
- [ ] Không có unnecessary complexity hoặc over-engineering

## Functionality

- [ ] Code address tất cả acceptance criteria
- [ ] Business rules từ project-context/ được respect
- [ ] Edge cases được xử lý
- [ ] Error handling có cho tất cả error paths

## Scope

- [ ] Không có unrelated refactoring hoặc modifications
- [ ] Không introduce new dependencies (trừ khi approved)
- [ ] Không có changes ngoài ticket scope

## Code Quality

- [ ] Coding standards (AGENTS.md Section 7.2) được tuân theo
- [ ] Code readable và maintainable
- [ ] Không có dead code, commented code, hoặc debugging artifacts
- [ ] Không có hardcoded values (credentials, URLs, environment-specific)

## Security

- [ ] Không có SQL injection, XSS, hoặc CSRF vulnerabilities
- [ ] Authentication và authorization checks có ở nơi cần thiết
- [ ] Không có sensitive data expose trong logs hoặc responses
- [ ] Input validation được thực hiện

## Performance

- [ ] Không có N+1 queries hoặc unnecessary database calls
- [ ] Không có performance regressions ở critical paths
- [ ] Caching được consider ở nơi phù hợp

## Testing

- [ ] Unit tests viết cho logic mới (nếu có test framework)
- [ ] Existing tests vẫn pass
- [ ] Test coverage adequate cho change
