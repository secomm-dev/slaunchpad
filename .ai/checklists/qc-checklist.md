# QC Checklist

## Test Coverage

- [ ] Tất cả acceptance criteria có test case tương ứng
- [ ] Happy path test cho mỗi AC
- [ ] Edge cases test (empty state, boundary values, max values)
- [ ] Negative cases test (invalid input, error states, unauthorized access)
- [ ] Business rules liên quan đến feature đã validate
- [ ] Regression testing trên affected areas đã hoàn thành

## Functional Testing

- [ ] Feature hoạt động đúng spec
- [ ] UI match design/mockup (nếu applicable)
- [ ] Error messages user-friendly
- [ ] Data validation hoạt động đúng
- [ ] Cross-browser testing đã hoàn thành (Chrome, Firefox, Safari — tối thiểu)

## Integration Testing

- [ ] API calls hoạt động đúng với payload đúng
- [ ] Third-party integration endpoints respond như mong đợi
- [ ] Webhook payloads được format đúng

## Documentation

- [ ] Test results được document (pass/fail per TC)
- [ ] Failed items có đủ detail để dev reproduce
- [ ] Regression risks được document cho release này
- [ ] QC signoff được provide bằng văn bản
