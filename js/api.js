// StashSmart Frontend API Helper
class StashSmartAPI {
  constructor() {
    this.baseURL = "/StashSmart/backend/api";
    this.token = localStorage.getItem("stashsmart_token");
  }

  // Get authorization headers
  getHeaders() {
    const headers = {
      "Content-Type": "application/json",
    };

    if (this.token) {
      headers["Authorization"] = `Bearer ${this.token}`;
    }

    return headers;
  }

  // Make API request
  async request(endpoint, options = {}) {
    const url = `${this.baseURL}${endpoint}`;
    const config = {
      headers: this.getHeaders(),
      ...options,
    };

    try {
      const response = await fetch(url, config);
      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || "API request failed");
      }

      return data;
    } catch (error) {
      console.error("API request failed:", error);
      throw error;
    }
  }

  // Authentication methods
  async register(userData) {
    return this.request("/auth/register", {
      method: "POST",
      body: JSON.stringify(userData),
    });
  }

  async login(credentials) {
    return this.request("/auth/login", {
      method: "POST",
      body: JSON.stringify(credentials),
    });
  }

  async logout() {
    const result = await this.request("/auth/logout", {
      method: "POST",
    });

    // Clear local storage
    localStorage.removeItem("stashsmart_token");
    localStorage.removeItem("stashsmart_user");

    return result;
  }

  async getProfile() {
    return this.request("/auth/profile");
  }

  async updateProfile(profileData) {
    return this.request("/auth/profile/update", {
      method: "PUT",
      body: JSON.stringify(profileData),
    });
  }

  async changePassword(passwordData) {
    return this.request("/auth/change-password", {
      method: "PUT",
      body: JSON.stringify(passwordData),
    });
  }

  // Transaction methods
  async getTransactions(params = {}) {
    const queryString = new URLSearchParams(params).toString();
    const endpoint = `/transactions${queryString ? "?" + queryString : ""}`;
    return this.request(endpoint);
  }

  async createTransaction(transactionData) {
    return this.request("/transactions", {
      method: "POST",
      body: JSON.stringify(transactionData),
    });
  }

  async getTransaction(id) {
    return this.request(`/transactions/${id}`);
  }

  async updateTransaction(id, transactionData) {
    return this.request(`/transactions/${id}`, {
      method: "PUT",
      body: JSON.stringify(transactionData),
    });
  }

  async deleteTransaction(id) {
    return this.request(`/transactions/${id}`, {
      method: "DELETE",
    });
  }

  async getAnalytics(params = {}) {
    const queryString = new URLSearchParams(params).toString();
    const endpoint = `/transactions/analytics${
      queryString ? "?" + queryString : ""
    }`;
    return this.request(endpoint);
  }

  // Category methods
  async getCategories() {
    return this.request("/categories");
  }

  async createCategory(categoryData) {
    return this.request("/categories", {
      method: "POST",
      body: JSON.stringify(categoryData),
    });
  }

  // Account methods
  async getAccounts() {
    return this.request("/accounts");
  }

  async createAccount(accountData) {
    return this.request("/accounts", {
      method: "POST",
      body: JSON.stringify(accountData),
    });
  }

  // Goal methods
  async getGoals() {
    return this.request("/goals");
  }

  async createGoal(goalData) {
    return this.request("/goals", {
      method: "POST",
      body: JSON.stringify(goalData),
    });
  }

  // Budget methods
  async getBudgets() {
    return this.request("/budgets");
  }

  async createBudget(budgetData) {
    return this.request("/budgets", {
      method: "POST",
      body: JSON.stringify(budgetData),
    });
  }

  // Utility methods
  isAuthenticated() {
    return !!this.token;
  }

  getCurrentUser() {
    const userStr = localStorage.getItem("stashsmart_user");
    return userStr ? JSON.parse(userStr) : null;
  }

  redirectToLogin() {
    window.location.href = "/StashSmart/login/login.html";
  }

  redirectToDashboard() {
    window.location.href = "/StashSmart/Finance-Dashboard/dashboard.html";
  }
}

// Authentication guard for protected pages
function requireAuth() {
  const api = new StashSmartAPI();

  if (!api.isAuthenticated()) {
    api.redirectToLogin();
    return false;
  }

  return true;
}

// Format currency
function formatCurrency(amount) {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
  }).format(amount);
}

// Format date
function formatDate(date) {
  return new Date(date).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

// Show loading spinner
function showLoading(element) {
  if (element) {
    element.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
  }
}

// Hide loading spinner
function hideLoading(element, originalContent) {
  if (element) {
    element.innerHTML = originalContent;
  }
}

// Show notification
function showNotification(message, type = "info") {
  // Create notification element
  const notification = document.createElement("div");
  notification.className = `notification notification-${type}`;
  notification.innerHTML = `
        <i class="fas fa-${
          type === "success"
            ? "check-circle"
            : type === "error"
            ? "exclamation-circle"
            : "info-circle"
        }"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" class="notification-close">
            <i class="fas fa-times"></i>
        </button>
    `;

  // Add styles if not already added
  if (!document.querySelector("#notification-styles")) {
    const styles = document.createElement("style");
    styles.id = "notification-styles";
    styles.textContent = `
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 5px;
                color: white;
                z-index: 1000;
                display: flex;
                align-items: center;
                gap: 10px;
                max-width: 400px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                animation: slideIn 0.3s ease-out;
            }
            .notification-success { background-color: #28a745; }
            .notification-error { background-color: #dc3545; }
            .notification-info { background-color: #007bff; }
            .notification-close {
                background: none;
                border: none;
                color: white;
                cursor: pointer;
                margin-left: auto;
            }
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
    document.head.appendChild(styles);
  }

  document.body.appendChild(notification);

  // Auto remove after 5 seconds
  setTimeout(() => {
    if (notification.parentElement) {
      notification.remove();
    }
  }, 5000);
}

// Global API instance
const api = new StashSmartAPI();
