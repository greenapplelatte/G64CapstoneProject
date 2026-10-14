document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("tripForm");
    
    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            
            const route = document.getElementById("routeSelect").value;
            const vehicle = document.getElementById("vehicleInput").value;
            const passengers = document.getElementById("passengerInput").value;
            
            if (!route || !vehicle || !passengers) {
                alert("Please fill in all fields.");
                return;
            }

            const tableBody = document.getElementById("manifestTableBody");
            const newRow = `
                <tr>
                    <td>TRIP-00${tableBody.rows.length + 1}</td>
                    <td>${route}</td>
                    <td>${vehicle}</td>
                    <td>${passengers}</td>
                    <td><span class="badge badge-normal">Active</span></td>
                </tr>
            `;
            
            tableBody.insertAdjacentHTML("afterbegin", newRow);
            form.reset();
            alert("Trip successfully logged!");
        });
    }
});