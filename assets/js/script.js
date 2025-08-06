$(document).ready(function () {
  console.log("Mandata LMS script loaded successfully!");

  // --- MOBILE MENU TOGGLE ---
  $('.mobile-menu-toggle').click(function() {
      $('.main-nav').toggleClass('nav-open');
  });

  // --- PRODUCT MANAGEMENT ---
  if ($("#row-product-1").length || $(".data-table tbody tr").first().attr('id')?.startsWith('row-product-')) {
    const productApi = "api_product_handler.php";
    $(".data-table").on("click", ".edit-product-btn", function () {
        const row = $(this).closest("tr");
        row.find(".view-mode").hide();
        row.find(".edit-mode").show();
        $(this).hide();
        row.find(".save-product-btn").show();
    });
    $(".data-table").on("click", ".save-product-btn", function () {
        const row = $(this).closest("tr");
        const id = $(this).data("id");
        const newName = row.find(".edit-product-name").val();
        $.ajax({
            url: productApi, type: "POST", dataType: "json",
            data: { action: "update_product", id: id, name: newName },
            success: function (response) {
                if (response.status === "success") {
                    row.find(".view-mode").text(newName).show();
                    row.find(".edit-mode").hide();
                    row.find(".save-product-btn").hide();
                    row.find(".edit-product-btn").show();
                } else { alert("Error: " + response.message); }
            },
        });
    });
    $(".data-table").on("click", ".delete-product-btn", function () {
        if (!confirm("Are you sure? Deleting a product will also delete its categories and courses.")) { return; }
        const id = $(this).data("id");
        $.ajax({
            url: productApi, type: "POST", dataType: "json",
            data: { action: "delete_product", id: id },
            success: function (response) {
                if (response.status === "success") {
                    $("#row-product-" + id).fadeOut(300, function () { $(this).remove(); });
                } else { alert("Error: " + response.message); }
            },
        });
    });
  }


  // --- CATEGORY MANAGEMENT ---
  if ($("#row-category-1").length || $(".data-table tbody tr").first().attr('id')?.startsWith('row-category-')) {
    const categoryApi = "api_category_handler.php";
    $(".data-table").on("click", ".edit-category-btn", function () {
        const row = $(this).closest("tr");
        row.find(".view-mode").hide();
        row.find(".edit-mode").show();
        $(this).hide();
        row.find(".save-category-btn").show();
    });
    $(".data-table").on("click", ".save-category-btn", function () {
        const row = $(this).closest("tr");
        const id = $(this).data("id");
        const newName = row.find(".edit-category-name").val();
        const productId = row.find(".edit-category-product").val();
        const productName = row.find(".edit-category-product option:selected").text();
        $.ajax({
            url: categoryApi, type: "POST", dataType: "json",
            data: { action: "update_category", id: id, name: newName, product_id: productId },
            success: function (response) {
                if (response.status === "success") {
                    row.find("td:eq(0) .view-mode").text(newName);
                    row.find("td:eq(1) .view-mode").text(productName);
                    row.find(".view-mode").show();
                    row.find(".edit-mode").hide();
                    row.find(".save-category-btn").hide();
                    row.find(".edit-category-btn").show();
                } else { alert("Error: " + response.message); }
            },
        });
    });
    $(".data-table").on("click", ".delete-category-btn", function () {
        if (!confirm("Are you sure you want to delete this category?")) { return; }
        const id = $(this).data("id");
        $.ajax({
            url: categoryApi, type: "POST", dataType: "json",
            data: { action: "delete_category", id: id },
            success: function (response) {
                if (response.status === "success") {
                    $("#row-category-" + id).fadeOut(300, function () { $(this).remove(); });
                } else { alert("Error: " + response.message); }
            },
        });
    });
  }


  // --- USER MANAGEMENT (MULTI-ROLE) ---
  if ($("#edit-user-modal").length) {
    const userApi = "api_user_handler.php";
    const modal = $("#edit-user-modal");
    
    $(".data-table").on("click", ".edit-user-btn", function () {
        const userId = $(this).data("id");
        $.ajax({
            url: 'api_get_user_details.php',
            type: 'GET',
            data: { id: userId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const userData = response.data;
                    $("#edit-user-id").val(userData.user_id);
                    $("#edit-username-display").text(userData.username);
                    
                    $('#edit-roles-container input[type="checkbox"]').prop('checked', false);
                    $('#edit-manager-id').val(userData.manager_id || "");

                    if (userData.roles && userData.roles.length) {
                        userData.roles.forEach(roleId => {
                            $(`#edit-roles-container input[value="${roleId}"]`).prop('checked', true);
                        });
                    }
                    modal.dialog("open");
                } else {
                    alert('Error: ' + response.message);
                }
            }
        });
    });

    modal.dialog({
        autoOpen: false,
        modal: true,
        width: 400,
        buttons: {
            "Save Changes": function() {
                const formData = $("#edit-user-form").serialize();
                $.ajax({
                    url: userApi,
                    type: 'POST',
                    data: formData + '&action=update_user',
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            location.reload(); 
                        } else {
                            alert('Error: ' + response.message);
                        }
                    }
                });
            },
            Cancel: function() {
                $(this).dialog("close");
            }
        }
    });

    $(".data-table").on("click", ".delete-user-btn", function () {
      if (!confirm("Are you sure? Deleting a user is permanent and cannot be undone.")) { return; }
      const id = $(this).data("id");
      $.ajax({
        url: userApi, 
        type: "POST", 
        dataType: "json",
        data: { action: "delete_user", id: id },
        success: function (response) {
          if (response.status === "success") {
            $("#row-user-" + id).fadeOut(300, function () { $(this).remove(); });
          } else { 
            alert("Error: " + response.message); 
          }
        },
        error: function() {
            alert("An unexpected error occurred while trying to delete the user.");
        }
      });
    });
  }

  // --- COURSE MANAGEMENT ---
  if ($("#row-course-1").length || $(".data-table tbody tr").first().attr('id')?.startsWith('row-course-')) {
      const courseApi = "api_course_handler.php";
      $('.data-table').on('click', '.edit-course-btn', function() {
          const row = $(this).closest('tr');
          row.find('.view-mode').hide();
          row.find('.edit-mode').show();
          $(this).hide();
          row.find('.save-course-btn').show();
      });
      $('.data-table').on('click', '.save-course-btn', function() {
          const row = $(this).closest('tr');
          const id = $(this).data('id');
          const name = row.find('.edit-course-name').val();
          const description = row.find('.edit-course-description').val();
          const category_id = row.find('.edit-course-category').val();
          const category_name = row.find('.edit-course-category option:selected').text();
          $.ajax({
              url: courseApi, type: 'POST', dataType: 'json',
              data: { action: 'update_course', id, name, description, category_id },
              success: function(response) {
                  if (response.status === 'success') {
                      row.find('td:eq(0) .view-mode').text(name);
                      row.find('td:eq(1) .view-mode').text(category_name);
                      row.find('td:eq(2) .view-mode').text(description);
                      row.find('.view-mode').show();
                      row.find('.edit-mode').hide();
                      row.find('.save-course-btn').hide();
                      row.find('.edit-course-btn').show();
                  } else { alert('Error: ' + response.message); }
              }
          });
      });
      $('.data-table').on('click', '.delete-course-btn', function() {
          if (!confirm('Are you sure you want to delete this course?')) { return; }
          const id = $(this).data('id');
          $.ajax({
              url: courseApi, type: 'POST', dataType: 'json',
              data: { action: 'delete_course', id: id },
              success: function(response) {
                  if (response.status === 'success') {
                      $('#row-course-' + id).fadeOut(300, function() { $(this).remove(); });
                  } else { alert('Error: ' + response.message); }
              }
          });
      });
  }

  // --- SLIDESHOW LOGIC ---
  if ($(".slideshow-container").length) {
    let slideIndex = 0;
    showSlides(slideIndex);
    function showSlides(n) {
      let slides = $(".slide");
      if (n >= slides.length) { slideIndex = 0; }
      if (n < 0) { slideIndex = slides.length - 1; }
      slides.hide();
      slides.eq(slideIndex).show();
    }
    $(".prev-slide").click(function () {
      showSlides(--slideIndex);
    });
    $(".next-slide").click(function () {
      showSlides(++slideIndex);
    });
  }

  // --- ADMIN DASHBOARD CHARTS ---
  if ($('#coursePerformanceChart').length) {
    let coursePerfChart, userBreakdownChart, avgScoreChart, questionBreakdownChart, onTimeCourseChart, onTimeUserChart;

    $('#productFilter, #categoryFilter').on('change', function() {
        updateCharts();
    });
    
     $('#productFilter').on('change', function() {
        const productId = $(this).val();
        const categorySelect = $('#categoryFilter');
        categorySelect.html('<option value="">Loading...</option>').prop('disabled', true);

        if (!productId) {
            categorySelect.html('<option value="">All Categories</option>').prop('disabled', false);
            return;
        }

        $.ajax({
            url: 'api_get_categories.php',
            type: 'GET',
            data: { product_id: productId },
            dataType: 'json',
            success: function(categories) {
                let options = '<option value="">All Categories</option>';
                if (categories && categories.length > 0) {
                    categories.forEach(function(category) {
                        options += `<option value="${category.category_id}">${category.category_name}</option>`;
                    });
                }
                categorySelect.html(options).prop('disabled', false);
            }
        });
    });
    
    function createCharts(chartData) {
        if (coursePerfChart) coursePerfChart.destroy();
        if (userBreakdownChart) userBreakdownChart.destroy();
        if (avgScoreChart) avgScoreChart.destroy();
        if (questionBreakdownChart) questionBreakdownChart.destroy();
        if (onTimeCourseChart) onTimeCourseChart.destroy();
        if (onTimeUserChart) onTimeUserChart.destroy();

        const courseLabels = chartData.course_performance ? chartData.course_performance.map(d => d.course_name) : [];
        coursePerfChart = new Chart(document.getElementById('coursePerformanceChart'), {
            type: 'bar', data: { labels: courseLabels, datasets: [{ label: 'Passes', data: chartData.course_performance ? chartData.course_performance.map(d => d.pass_count) : [], backgroundColor: 'rgba(56, 142, 60, 0.7)' }, { label: 'Fails', data: chartData.course_performance ? chartData.course_performance.map(d => d.fail_count) : [], backgroundColor: 'rgba(211, 47, 47, 0.7)' }] },
            options: { scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });

        const userLabels = chartData.user_breakdown ? chartData.user_breakdown.map(d => d.username) : [];
        userBreakdownChart = new Chart(document.getElementById('userBreakdownChart'), {
            type: 'bar',
            data: {
                labels: userLabels,
                datasets: [
                    { label: 'Completed', data: chartData.user_breakdown ? chartData.user_breakdown.map(d => d.completed_count) : [], backgroundColor: 'rgba(56, 142, 60, 0.7)' },
                    { label: 'Failed', data: chartData.user_breakdown ? chartData.user_breakdown.map(d => d.failed_count) : [], backgroundColor: 'rgba(211, 47, 47, 0.7)' },
                    { label: 'In Progress', data: chartData.user_breakdown ? chartData.user_breakdown.map(d => d.in_progress_count) : [], backgroundColor: 'rgba(0, 168, 232, 0.7)' },
                    { label: 'Not Started', data: chartData.user_breakdown ? chartData.user_breakdown.map(d => d.not_started_count) : [], backgroundColor: 'rgba(108, 117, 125, 0.7)' }
                ]
            },
            options: { plugins: { title: { display: true, text: 'Course Status per User' } }, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } } } }
        });

        const avgScoreLabels = chartData.average_score ? chartData.average_score.map(d => d.course_name) : [];
        avgScoreChart = new Chart(document.getElementById('averageScoreChart'), {
            type: 'bar', data: { labels: avgScoreLabels, datasets: [{ label: 'Average Score (%)', data: chartData.average_score ? chartData.average_score.map(d => parseFloat(d.average_score).toFixed(2)) : [], backgroundColor: 'rgba(0, 90, 156, 0.7)' }] },
            options: { scales: { y: { beginAtZero: true, max: 100 } }, plugins: { legend: { display: false } } }
        });

        const questionLabels = chartData.question_breakdown ? chartData.question_breakdown.map(d => `${d.course_name} - ${d.question_title}`) : [];
        questionBreakdownChart = new Chart(document.getElementById('questionBreakdownChart'), {
            type: 'bar',
            data: {
                labels: questionLabels,
                datasets: [
                    { label: 'Correct Answers', data: chartData.question_breakdown ? chartData.question_breakdown.map(d => d.correct_count) : [], backgroundColor: 'rgba(56, 142, 60, 0.7)' },
                    { label: 'Incorrect Answers', data: chartData.question_breakdown ? chartData.question_breakdown.map(d => d.incorrect_count) : [], backgroundColor: 'rgba(211, 47, 47, 0.7)' }
                ]
            },
            options: { indexAxis: 'y', scales: { x: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } }, y: { stacked: true } } }
        });

        if ($('#onTimeCourseChart').length && chartData.on_time_course) {
            const onTimeCourseLabels = chartData.on_time_course.map(d => d.course_name);
            onTimeCourseChart = new Chart(document.getElementById('onTimeCourseChart'), {
                type: 'bar',
                data: {
                    labels: onTimeCourseLabels,
                    datasets: [
                        { label: 'On Time', data: chartData.on_time_course.map(d => d.on_time_count), backgroundColor: 'rgba(56, 142, 60, 0.7)' },
                        { label: 'Late', data: chartData.on_time_course.map(d => d.late_count), backgroundColor: 'rgba(211, 47, 47, 0.7)' }
                    ]
                },
                options: { scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
            });
        }
        if ($('#onTimeUserChart').length && chartData.on_time_user) {
            const onTimeUserLabels = chartData.on_time_user.map(d => d.username);
            onTimeUserChart = new Chart(document.getElementById('onTimeUserChart'), {
                type: 'bar',
                data: {
                    labels: onTimeUserLabels,
                    datasets: [
                        { label: 'On Time', data: chartData.on_time_user.map(d => d.on_time_count), backgroundColor: 'rgba(56, 142, 60, 0.7)' },
                        { label: 'Late', data: chartData.on_time_user.map(d => d.late_count), backgroundColor: 'rgba(211, 47, 47, 0.7)' }
                    ]
                },
                options: {
                    plugins: { title: { display: true, text: 'Completion Status per User' } },
                    scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } } }
                }
            });
        }
    }

    function updateCharts() {
        const productId = $('#productFilter').val();
        const categoryId = $('#categoryFilter').val();
        $.ajax({
            url: 'api_chart_data.php',
            type: 'GET',
            data: { product_id: productId, category_id: categoryId },
            dataType: 'json',
            success: function(data) {
                createCharts(data);
            },
            error: function(xhr, status, error) {
                console.error("Failed to fetch chart data:", status, error, xhr.responseText);
            }
        });
    }
    updateCharts();
  }

  // --- COURSE CONTENT MANAGEMENT (DELETE AND REORDER) ---
  if ($("#sortable-content").length) {
    $("#sortable-content").on("click", ".delete-content-btn", function () {
        if (!confirm("Are you sure you want to permanently delete this content item?")) { return; }
        const id = $(this).data("id");
        const row = $("#content_" + id);
        $.ajax({
            url: "api_delete_content.php",
            type: "POST",
            data: { id: id },
            dataType: "json",
            success: function (response) {
                if (response.status === "success") {
                    row.fadeOut(300, function () { $(this).remove(); });
                } else { alert("Error: " + response.message); }
            },
        });
    });
    $("#sortable-content").sortable({
        placeholder: "ui-state-highlight",
        update: function(event, ui) {
            var newOrder = $(this).sortable('serialize');
            $.ajax({
                url: 'api_reorder_content.php',
                type: 'POST',
                data: newOrder,
                dataType: 'json',
                success: function(response) {
                    if (response && response.status === 'success') {
                        $('#sortable-content tr').each(function(index) {
                            $(this).find('td:first').text(index + 1);
                        });
                    } else {
                        alert('Error: Could not save the new order.');
                    }
                }
            });
        }
    }).disableSelection();
  }

  // --- DYNAMIC FORM FIELDS FOR manage_course_content.php ---
  if ($("#add-content-form").length) {
      $("#add-content-form").on("submit", function() {
          if (tinymce.get('text-editor')) {
              tinymce.triggerSave();
          }
      });
      function initTinyMCE() {
          tinymce.init({
              selector: '#text-editor',
              plugins: 'lists link image table code help wordcount',
              toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright | bullist numlist outdent indent | link image | code | help'
          });
      }
      function generateAnswerFields(count) {
          const container = document.getElementById('answer-options-container');
          const correctAnsContainer = document.getElementById('correct-answer-container');
          if (!container || !correctAnsContainer) return;
          let optionsHTML = '';
          let correctAnsHTML = '<option value="">-- Select --</option>';
          const labels = ['A', 'B', 'C', 'D'];
          for (let i = 0; i < count; i++) {
              optionsHTML += `<div class="form-group"><label>Option ${labels[i]}</label><input type="text" name="options[]" required></div>`;
              correctAnsHTML += `<option value="${labels[i].toLowerCase()}">${labels[i]}</option>`;
          }
          container.innerHTML = optionsHTML;
          correctAnsContainer.innerHTML = correctAnsHTML;
      }
      function updateFormFields() {
          const container = document.getElementById('dynamic-content-fields');
          const type = document.getElementById('content_type_select').value;
          let html = '';
          if (tinymce.get('text-editor')) {
              tinymce.remove('#text-editor');
          }
          switch(type) {
              case 'text':
                  html = `<div class="form-group"><label>Text Content</label><textarea id="text-editor" name="content_text" rows="15"></textarea></div>`;
                  break;
              case 'video':
                  html = `<div class="form-group"><label>Video File</label><input type="file" name="content_video_file" accept="video/*" required></div>`;
                  break;
              case 'image':
                  html = `<div class="form-group"><label>Image File</label><input type="file" name="content_image_file" accept="image/*" required></div>`;
                  break;
              case 'image_gallery':
                  html = `<div id="gallery-items-container"><div class="gallery-item-pair"><div class="form-group"><label>Image 1</label><input type="file" name="gallery_images[]" accept="image/*" required></div><div class="form-group"><label>Associated Text 1</label><textarea name="gallery_texts[]" rows="2" style="width:100%"></textarea></div><hr></div></div><button type="button" class="button" onclick="addGalleryItem()">Add More Content</button>`;
                  break;
              case 'quiz_inline':
              case 'quiz_final':
                  html = `<div class="form-group"><label>Question</label><input type="text" name="quiz_question" required></div><div class="form-group"><label>Supporting Image (Optional)</label><input type="file" name="quiz_image" accept="image/*"></div><div class="form-group"><label>Number of Answers</label><select id="answer_count_select" style="width:100%; padding: 8px;"><option value="4" selected>4</option><option value="2">2 (True/False)</option><option value="3">3</option></select></div><div id="answer-options-container"></div><div class="form-group"><label>Correct Answer</label><select name="correct_answer" id="correct-answer-container" required style="width:100%; padding: 8px;"></select></div>`;
                  break;
          }
          container.innerHTML = html;
          if (type === 'text') { initTinyMCE(); }
          if (type === 'quiz_inline' || type === 'quiz_final') {
              generateAnswerFields(document.getElementById('answer_count_select').value);
          }
      }
      $('#content_type_select').on('change', updateFormFields);
      $('#dynamic-content-fields').on('change', '#answer_count_select', function() {
          generateAnswerFields($(this).val());
      });
      updateFormFields();
      window.addGalleryItem = function() {
          const container = document.getElementById('gallery-items-container');
          const newItem = document.createElement('div');
          newItem.className = 'gallery-item-pair';
          newItem.innerHTML = `<div class="form-group"><label>Image</label><input type="file" name="gallery_images[]" accept="image/*" required></div><div class="form-group"><label>Associated Text</label><textarea name="gallery_texts[]" rows="2" style="width:100%"></textarea></div><hr>`;
          container.appendChild(newItem);
      }
  }

  // --- DYNAMIC COURSE CATALOG FILTER ---
  if ($("#enroll_product_filter").length) {
    $('#enroll_product_filter, #enroll_category_filter').on('change', function() {
        loadAvailableCourses();
    });

    $('#enroll_product_filter').on('change', function() {
        const productId = $(this).val();
        const categorySelect = $('#enroll_category_filter');
        categorySelect.html('<option value="">Loading...</option>').prop('disabled', true);

        $.ajax({
            url: 'admin/api_get_categories.php',
            type: 'GET',
            data: { product_id: productId },
            dataType: 'json',
            success: function(categories) {
                let options = '<option value="">All Categories</option>';
                if (categories && categories.length > 0) {
                    categories.forEach(function(category) {
                        options += `<option value="${category.category_id}">${category.category_name}</option>`;
                    });
                }
                categorySelect.html(options).prop('disabled', false);
            }
        });
    });
    
    function loadAvailableCourses() {
        const productId = $("#enroll_product_filter").val();
        const categoryId = $("#enroll_category_filter").val();
        const container = $("#course-catalog-container");
        container.html('<div class="card"><p>Loading courses...</p></div>');
        $.ajax({
            url: 'api_get_available_courses.php',
            type: 'GET',
            data: { product_id: productId, category_id: categoryId },
            dataType: 'json',
            success: function(courses) {
                container.empty();
                if (courses && courses.length > 0) {
                    courses.forEach(function(course) {
                        const courseCard = `
                            <div class="card course-card">
                                <div>
                                    <h5>${course.course_name}</h5>
                                    <p>${course.course_description}</p>
                                </div>
                                <form action="enroll.php" method="POST" style="margin-top: auto;">
                                    <input type="hidden" name="course_id" value="${course.course_id}">
                                    <button type="submit" name="enroll" class="button">Enroll</button>
                                </form>
                            </div>`;
                        container.append(courseCard);
                    });
                } else {
                    container.html('<div class="card" style="grid-column: 1 / -1;"><p>There are no courses available with the selected filters.</p></div>');
                }
            }
        });
    }
    
    $('#enroll_product_filter').trigger('change');
  }


  // --- DYNAMIC COURSE FILTER ON ASSIGN PAGE ---
  if ($("#product_filter").length) { 
    $("#product_filter, #category_filter").on('change', function() {
        // This is just to ensure the logic flows, no action needed here.
    });

    $("#product_filter").on('change', function() {
        const productId = $(this).val();
        const categorySelect = $("#category_filter");
        const courseSelect = $("#course_select");
        categorySelect.html('<option value="">Loading...</option>').prop('disabled', true);
        courseSelect.html('<option value="">-- Select a Category First --</option>').prop('disabled', true);

        if (!productId) {
            categorySelect.html('<option value="">-- Select a Product First --</option>');
            return;
        }
        $.ajax({
            url: 'api_get_categories.php',
            type: 'GET',
            dataType: 'json',
            data: { product_id: productId },
            success: function(categories) {
                let options = '<option value="">-- Choose a Category --</option>';
                if (categories && categories.length > 0) {
                    categories.forEach(cat => options += `<option value="${cat.category_id}">${cat.category_name}</option>`);
                }
                categorySelect.html(options).prop('disabled', false);
            }
        });
    });

    $("#category_filter").on('change', function() {
        const categoryId = $(this).val();
        const courseSelect = $("#course_select");
        courseSelect.html('<option value="">Loading...</option>').prop('disabled', true);
        if (!categoryId) {
            courseSelect.html('<option value="">-- Select a Category First --</option>');
            return;
        }
        $.ajax({
            url: 'api_get_courses.php',
            type: 'GET',
            data: { category_id: categoryId },
            dataType: 'json',
            success: function(courses) {
                let options = '<option value="">-- Choose a Course --</option>';
                if (courses && courses.length > 0) {
                    courses.forEach(function(course) {
                        options += `<option value="${course.course_id}">${course.course_name}</option>`;
                    });
                    courseSelect.prop('disabled', false);
                } else {
                    options = '<option value="">-- No Courses in this Category --</option>';
                }
                courseSelect.html(options);
            }
        });
    });
  }

  // --- DYNAMIC COURSE DEALLOCATION ---
  if ($(".deallocate-btn").length) {
    $(".data-table").on("click", ".deallocate-btn", function() {
        if (!confirm("Are you sure you want to deallocate this course from the user?")) { return; }
        const id = $(this).data("id");
        const row = $("#enrollment-row-" + id);
        $.ajax({
            url: 'api_deallocate_course.php',
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    row.fadeOut(300, function() { $(this).remove(); });
                } else {
                    alert('Error: ' + (response.message || 'Could not deallocate course.'));
                }
            }
        });
    });
  }
});