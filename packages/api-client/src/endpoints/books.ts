import type { AxiosInstance } from 'axios';
import type {
    Book,
    BookFilters,
    BookInput,
    BookReading,
    BookReadingInput,
    BookTask,
    BookTaskInput,
    Resource,
} from '@focus/types';
import type { RequestOptions } from './types';

export function bookEndpoints(http: AxiosInstance) {
    return {
        async list(filters: BookFilters = {}, options: RequestOptions = {}): Promise<Book[]> {
            const { data } = await http.get<Resource<Book[]>>('/books', { params: filters, ...options });
            return data.data;
        },
        async get(id: number, options: RequestOptions = {}): Promise<Book> {
            const { data } = await http.get<Resource<Book>>(`/books/${id}`, options);
            return data.data;
        },
        async create(input: BookInput): Promise<Book> {
            const { data } = await http.post<Resource<Book>>('/books', input);
            return data.data;
        },
        async update(id: number, input: BookInput): Promise<Book> {
            const { data } = await http.patch<Resource<Book>>(`/books/${id}`, input);
            return data.data;
        },
        async remove(id: number): Promise<void> {
            await http.delete(`/books/${id}`);
        },
        async logReading(bookId: number, input: BookReadingInput): Promise<BookReading> {
            const { data } = await http.post<Resource<BookReading>>(`/books/${bookId}/readings`, input);
            return data.data;
        },
        async updateReading(id: number, input: BookReadingInput): Promise<BookReading> {
            const { data } = await http.patch<Resource<BookReading>>(`/book-readings/${id}`, input);
            return data.data;
        },
        async removeReading(id: number): Promise<void> {
            await http.delete(`/book-readings/${id}`);
        },
        async addTask(bookId: number, input: BookTaskInput): Promise<BookTask> {
            const { data } = await http.post<Resource<BookTask>>(`/books/${bookId}/tasks`, input);
            return data.data;
        },
        async removeTask(bookId: number, taskId: number): Promise<void> {
            await http.delete(`/books/${bookId}/tasks/${taskId}`);
        },
    };
}
