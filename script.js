

document.addEventListener('DOMContentLoaded', function() {
  const commentForm = document.querySelector('.comment-form');
  const commentInput = commentForm.querySelector('input[name="comment"]');
  const commentGrid = document.querySelector('.comment-grid');

  commentForm.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const commentText = commentInput.value.trim();
    
    if (commentText === '') {
      alert('Please enter a comment!');
      return;
    }
//new comment creation
    const newComment = document.createElement('div');
    newComment.className = 'comment-card';
    
    newComment.innerHTML = `
      <div class="small-avatar"><img src="images/iconss.png" alt="User photo"></div>
      <div>
        <div class="who">You</div>
        <div class="txt">${commentText}</div>
      </div>
    `;
 
 //insert new comment before the old ones
    commentGrid.insertBefore(newComment, commentGrid.firstChild);
    commentInput.value = '';
  });
});
