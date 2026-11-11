import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed, fakeAsync, tick } from '@angular/core/testing';
import { ImportJob } from '../models';
import { importJob } from '../testing/fixtures';
import { ImportService } from './import.service';

describe('ImportService', () => {
  let service: ImportService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    service = TestBed.inject(ImportService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('загружает файл в multipart-поле file', () => {
    const file = new File(['x'], 'prices.xlsx');
    service.upload(file).subscribe();

    const req = http.expectOne('/api/imports');
    expect(req.request.method).toBe('POST');
    expect((req.request.body as FormData).get('file')).toEqual(jasmine.any(File));
    req.flush(importJob({ status: 'queued' }));
  });

  it('опрашивает статус до завершения импорта и останавливается', fakeAsync(() => {
    const seen: ImportJob[] = [];
    let completed = false;
    service.watch('job-1', 1000).subscribe({ next: (j) => seen.push(j), complete: () => (completed = true) });

    tick(0);
    http.expectOne('/api/imports/job-1').flush(importJob({ status: 'queued', progress: 0 }));
    tick(1000);
    http.expectOne('/api/imports/job-1').flush(importJob({ status: 'processing', progress: 50 }));
    tick(1000);
    http.expectOne('/api/imports/job-1').flush(importJob({ status: 'completed', progress: 100 }));
    tick(5000);

    expect(seen.map((j) => j.status)).toEqual(['queued', 'processing', 'completed']);
    expect(completed).toBeTrue();
    http.expectNone('/api/imports/job-1');
  }));

  it('не шлёт новый запрос, пока не пришёл ответ на предыдущий', fakeAsync(() => {
    const sub = service.watch('job-1', 1000).subscribe();
    tick(3000);

    const pending = http.match('/api/imports/job-1');
    expect(pending.length).toBe(1);
    pending[0].flush(importJob({ status: 'failed' }));
    sub.unsubscribe();
  }));
});
