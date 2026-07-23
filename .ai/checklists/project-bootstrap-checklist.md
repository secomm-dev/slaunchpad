# Project Bootstrap Checklist

## Pre-Bootstrap

- [ ] Client inputs đã gather (brief, SOW, technical docs)
- [ ] Source code access đã confirm (nếu existing project)
- [ ] Team đã assign và roles đã define
- [ ] Repository access đã confirm

## Blueprint

- [ ] PROJECT_AI_BLUEPRINT.md đã tạo
- [ ] Blueprint đã review với blueprint-validation-checklist.md
- [ ] Blueprint đã approve bởi SA/TL
- [ ] document_status đổi từ "draft" sang "reviewed"

## Generation

- [ ] Project context đã generate từ blueprint
- [ ] ai-toolkit/validation-report.md đã review
- [ ] Blocking issues đã fix
- [ ] Warnings đã review và accept
- [ ] Generated files match expected structure

## Commit

- [ ] AGENTS.md đã commit
- [ ] CLAUDE.md đã commit (thin wrapper đã verify)
- [ ] .claude/skills/ đã commit (relevant skills only)
- [ ] project-context/ đã commit (tất cả 08+ files)
- [ ] .github/copilot-instructions.md đã commit
- [ ] .github/pull_request_template.md đã commit
- [ ] ai-toolkit/ generation log đã commit

## Team

- [ ] AGENTS.md đã share với team
- [ ] Skills đã demonstrate (tối thiểu: task, review-code)
- [ ] First ticket đã assign (Mode C cho onboarding)
