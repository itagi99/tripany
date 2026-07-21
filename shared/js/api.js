const API_BASE = window.location.origin;

class Api {
  constructor() {
    this.token = localStorage.getItem('vehigo_token');
  }

  setToken(token) {
    this.token = token;
    localStorage.setItem('vehigo_token', token);
  }

  clearToken() {
    this.token = null;
    localStorage.removeItem('vehigo_token');
  }

  async request(path, options = {}) {
    const headers = {
      'Content-Type': 'application/json',
      ...options.headers
    };

    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`;
    }

    const response = await fetch(`${API_BASE}${path}`, {
      ...options,
      headers
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.error || 'Request failed');
    }

    return data;
  }

  get(path) {
    return this.request(path);
  }

  post(path, body) {
    return this.request(path, {
      method: 'POST',
      body: JSON.stringify(body)
    });
  }

  put(path, body) {
    return this.request(path, {
      method: 'PUT',
      body: JSON.stringify(body)
    });
  }

  delete(path) {
    return this.request(path, {
      method: 'DELETE'
    });
  }

  // Auth
  async register(data) {
    const result = await this.post('/api/auth/register', data);
    this.setToken(result.token);
    return result;
  }

  async login(data) {
    const result = await this.post('/api/auth/login', data);
    this.setToken(result.token);
    return result;
  }

  async getMe() {
    return this.get('/api/auth/me');
  }

  // Vehicles
  async getVehicles() {
    return this.get('/api/vehicles');
  }

  // Bookings
  async createBooking(data) {
    return this.post('/api/bookings', data);
  }

  async getMyBookings() {
    return this.get('/api/bookings/my');
  }

  async getBooking(id) {
    return this.get(`/api/bookings/${id}`);
  }

  async cancelBooking(id) {
    return this.put(`/api/bookings/${id}/cancel`);
  }

  async rateBooking(id, data) {
    return this.put(`/api/bookings/${id}/rate`, data);
  }

  // Coupons
  async validateCoupon(code, fare) {
    return this.post('/api/coupons/validate', { code, fare });
  }

  // Driver
  async updateDriverStatus(status) {
    return this.put('/api/driver/status', { status });
  }

  async getPendingBookings() {
    return this.get('/api/driver/bookings/pending');
  }

  async acceptBooking(id) {
    return this.put(`/api/driver/bookings/${id}/accept`);
  }

  async rejectBooking(id) {
    return this.put(`/api/driver/bookings/${id}/reject`);
  }

  async completeBooking(id) {
    return this.put(`/api/driver/bookings/${id}/complete`);
  }

  async getDriverBookings() {
    return this.get('/api/driver/bookings');
  }

  async getDriverStats() {
    return this.get('/api/driver/stats');
  }

  // Admin
  async getAdminStats() {
    return this.get('/api/admin/stats');
  }

  async getAllBookings() {
    return this.get('/api/admin/bookings');
  }

  async getAllDrivers() {
    return this.get('/api/admin/drivers');
  }

  async addVehicle(data) {
    return this.post('/api/admin/vehicles', data);
  }

  async updateVehicle(id, data) {
    return this.put(`/api/admin/vehicles/${id}`, data);
  }

  async deleteVehicle(id) {
    return this.delete(`/api/admin/vehicles/${id}`);
  }

  async getCoupons() {
    return this.get('/api/admin/coupons');
  }

  async addCoupon(data) {
    return this.post('/api/admin/coupons', data);
  }

  async deleteCoupon(id) {
    return this.delete(`/api/admin/coupons/${id}`);
  }

  async getBanners() {
    return this.get('/api/admin/banners');
  }

  async addBanner(data) {
    return this.post('/api/admin/banners', data);
  }

  async deleteBanner(id) {
    return this.delete(`/api/admin/banners/${id}`);
  }

  async getOffers() {
    return this.get('/api/admin/offers');
  }

  async addOffer(data) {
    return this.post('/api/admin/offers', data);
  }

  async deleteOffer(id) {
    return this.delete(`/api/admin/offers/${id}`);
  }
}

const api = new Api();
